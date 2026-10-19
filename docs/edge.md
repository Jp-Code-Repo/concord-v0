# Edge: use and purpose

The **edge** is Concord's front door. It is a single nginx container (`concord-edge`) and the **only** part of the stack reachable from the network. It is Concord's equivalent of `as-server` in the asqueue setup, and it lives in `infra/edge/` inside the same repo.

```
Browser ──▶ concord-edge :80 ──▶ iam      (php-fpm, FastCGI)
                          ├────▶ gate     (later)
                          ├────▶ hr       (later)
                          ├────▶ tickets  (later)
                          └────▶ notif    (later, WebSocket)
```

MySQL, Redis and every service are on the internal Docker network `concord-net` and publish no ports to the outside. (MySQL is bound to 127.0.0.1:3307 for local development only; remove that in production.)

---

## 1. What the edge is for

1. **One entry point.** Users and apps only ever talk to one address on port 80. Services can be added, moved or restarted without anyone's URL changing.
2. **Routing by path prefix.** Each service owns a prefix: `/api/iam/`, later `/api/hr/`, `/api/tickets/`, `/ws/`. The portal frontend is served at `/`. This is the same idea as `/asqueue/` and `/asq/api/` in `as-server`.
3. **Authentication before the request reaches a service.** For protected routes the edge asks IAM "who is this cookie?" first. If IAM says no, the request never reaches the service. If it says yes, the edge tells the service who the user is.
4. **First line of defense.** Rate limiting on login, security headers, hidden internal endpoints, and no direct access to services.
5. **The place where scaling and HTTPS happen later.** TLS, load balancing across several copies of a service, and WebSocket proxying all belong here.

## 2. Files

```
infra/edge/
├── nginx.conf                  all routing and rules
├── snippets/
│   ├── fastcgi-base.conf       FastCGI parameters that never change per request
│   └── fastcgi-laravel.conf    "forward this request to a Laravel service"
└── html/index.html             placeholder portal page (the React app replaces it later)
```

All three are mounted read-only into the container by `docker-compose.yml`.

## 3. How a request flows

### Public route (example: login)

```
POST /api/iam/login
  └─▶ edge: rate limit (30 requests/min per IP, burst 10)
        └─▶ FastCGI to iam:9000 ─▶ Laravel
```

IAM checks the cookie itself on routes such as `/me`, so these routes do not use `auth_request`.

### Protected route (the pattern every other service uses)

```
GET /api/hr/employees            (browser sends the concord_session cookie)
  │
  ├─ 1. edge runs a subrequest:  /_auth  ─▶ IAM  GET /api/iam/verify
  │        IAM looks up the session
  │          ├─ invalid/expired/revoked/disabled ─▶ 401  ─▶ edge answers 401, stops here
  │          └─ valid ─▶ 204 with header  X-User-Id: <user id>
  │
  ├─ 2. edge saves it:  auth_request_set $auth_user_id $upstream_http_x_user_id
  │
  └─ 3. edge forwards to HR with an extra FastCGI parameter:
           CONCORD_USER_ID = <user id>
```

Inside the service, read it as:

```php
$userId = $request->server('CONCORD_USER_ID');
```

**Why `CONCORD_USER_ID` and not a header?** Anything a client sends as an HTTP header arrives in PHP as `HTTP_<NAME>`. A FastCGI parameter named `CONCORD_USER_ID` has no `HTTP_` prefix, so a client cannot forge it, whatever headers it sends. This was tested with forged `X-User-Id` and `Concord-User-Id` headers: ignored.

**What the edge does not do.** It answers *who*, not *what they may do*. Each service still checks the specific block (for example `registrar.student-profile.chinese-name`) for every action, with IAM's data.

**Office context is not passed.** The edge currently passes only `CONCORD_USER_ID`. Whether an active office context exists is unresolved (OPEN-AUTH-001 in `iam-authorization-decisions.md`). If it is adopted, `verify` and `/_auth` must also return the office, and the edge must pass it as a FastCGI parameter (for example `CONCORD_OFFICE_ID`), never as a client-settable header. Do not add it until that decision is recorded.

## 4. What is in `nginx.conf`

| Location | What it does |
|---|---|
| `/` | Serves the portal from `html/`. `try_files` falls back to `index.html` so client-side routing (TanStack Router) works. |
| `= /api/iam/verify` | Returns **404** to anyone outside. It exists only for the edge's own subrequest. |
| `= /api/iam/login` | Rate limited, then forwarded to IAM. |
| `/api/iam/` | Public IAM endpoints (`logout`, `me`), forwarded to IAM. |
| `= /_auth` | `internal;` so it can only be reached by the edge itself. Calls IAM `verify` with no request body. |
| `= /api/iam/edge-check` | A self-test route that uses the full `auth_request` pattern. It echoes `CONCORD_USER_ID`. |
| commented `/api/hr/` | Template for the next protected service. |
| commented `/ws/` | Template for WebSockets (Reverb) with upgrade headers and a long read timeout. |

Global settings: `server_tokens off` (no nginx version leak), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, gzip for text assets, `client_max_body_size 20m`.

### Rate limiting caveat

The login limit is per client IP. In an institution many users can share one NAT address, so a burst at the start of the day could lock out a whole office. Plan to add per-account throttling inside IAM as well. Once HTTPS or any proxy sits in front of the edge, configure `real_ip` (`set_real_ip_from`, `real_ip_header`) so the limit sees the real client address rather than the proxy.

### Boots even when a service is down

Upstreams are written with variables (`set $laravel_upstream iam:9000; fastcgi_pass $laravel_upstream;`) and Docker's DNS resolver (`127.0.0.11`). nginx looks the name up when a request arrives, so the edge starts even if a service container is stopped (that request gets a 502). The same trick is used in `as-server`.

### The FastCGI snippets

The edge talks **FastCGI directly to php-fpm**. There is no second nginx inside each service, which is one hop fewer than asqueue. The snippets set `SCRIPT_FILENAME /var/www/public/index.php` (Laravel's front controller) and pass the original `REQUEST_URI` unchanged. Laravel therefore sees the real path, and routes live at `/api/<service>/...`. The edge does not rewrite URLs.

Parameters are written out explicitly instead of using `include fastcgi_params`, so none is ever sent twice.

### A trap that is already fixed

nginx subrequests **share variables with the request that started them**. An early version had `/_auth` set the same variables the parent location used, which would have sent HR requests to IAM. `/_auth` now uses its own variable (`$auth_upstream`) and literal parameters. Rule: **`/_auth` must never `set` a variable that another location reads.**

## 5. How to add a new service behind the edge

1. **Add it to `docker-compose.yml`.** Use the shared image `concord/php:8.3`, mount `./services/<name>:/var/www`, give it `DB_*` for its own database, put it on `concord-net`, and publish **no ports**. Create the database and user in `infra/mysql/init.sh`. (The init script only runs on a fresh MySQL volume, so for an existing database create it by hand.)
2. **Make its Laravel routes live under `/api/<name>/...`.** Laravel's `routes/api.php` already adds `/api`, so use `Route::prefix('<name>')`.
3. **Add a block to `nginx.conf`:**

   ```nginx
   location /api/hr/ {
       auth_request /_auth;
       auth_request_set $auth_user_id $upstream_http_x_user_id;
       set $laravel_upstream hr:9000;
       include snippets/fastcgi-laravel.conf;
       fastcgi_param CONCORD_USER_ID $auth_user_id;
   }
   ```

   For a public route (no login needed), leave out the three auth lines.
4. **Reload the edge:** `docker compose exec edge nginx -t`, then `docker compose exec edge nginx -s reload`.
5. **In the service,** read `$request->server('CONCORD_USER_ID')`. If it is missing, answer 401. It means the request did not come through the edge.

**Important:** a service must never be reachable except through the edge. If you publish its port, anyone can skip `auth_request`.

**Trust boundary on `concord-net`.** `CONCORD_USER_ID` is trusted because only the edge can set it. FastCGI itself has no authentication, so any other container on `concord-net` that can reach `hr:9000` could set that parameter to any value. This is acceptable while every container on the network is trusted. Rules that keep it so:

- Never put an untrusted or third-party container on `concord-net`.
- Do not publish php-fpm ports (9000) to the host.
- Later hardening: give services a separate internal network that only the edge and the service itself join, so services cannot reach each other's php-fpm directly.

## 6. Planned extensions

- **Portal frontend.** The React app (TanStack Router, shadcn/ui) gets built into static files that the edge serves at `/`, replacing `html/index.html`.
- **WebSockets.** Uncomment the `/ws/` template and point it at the notification service (Reverb, port 8080).
- **HTTPS.** Terminate TLS at the edge (port 443, certificate), redirect 80 to 443, then set `COOKIE_SECURE=true` so the session cookie travels only over HTTPS. Add HSTS once HTTPS is stable.
- **Cookie and CSRF policy.** The session is a cookie, so state-changing routes need a CSRF decision. Set the cookie `HttpOnly` and an explicit `SameSite` (Lax is a reasonable start), and decide how POST/PUT/DELETE routes are protected (SameSite alone, a CSRF token, or an Origin check at the edge). Record the choice here once made.
- **Load balancing.** If one copy of a service is not enough, define an `upstream` with several php-fpm containers. Services are stateless (sessions live in IAM and Redis), so this works without redesign. At 200 concurrent users, one copy of each is expected to be enough.
- **Caching.** Static assets can be cached freely. Do **not** cache the `/_auth` responses for more than a moment: disabling an account or revoking a grant has to take effect immediately. This depends on IAM rejecting disabled users and invalidated sessions in `verify` (DEC-AUTH-009).

## 7. Troubleshooting

| Symptom | Likely cause |
|---|---|
| **502 Bad Gateway** | The service container is stopped, crashed, or not on `concord-net`. Check `docker compose ps`. |
| **404 on `/api/iam/verify`** | Expected. The edge hides it from outside. |
| **401 on a protected route** | No valid session cookie, or IAM rejected it. Test `/api/iam/me` first. |
| **Service sees no `CONCORD_USER_ID`** | The location is missing the `auth_request_set` / `fastcgi_param` lines, or the service was reached directly. |
| **Config change has no effect** | Run `docker compose exec edge nginx -s reload`, and check `nginx -t` for errors first. |
| **Port 80 already in use** | Another web server (for example asqueue's `as-server`) is running. Stop it. |

Useful commands:

```bash
docker compose logs -f edge          # access and error log
docker compose exec edge nginx -t    # validate the config
docker compose exec edge nginx -s reload
./scripts/smoke.sh                   # end-to-end check through the edge
```

## 8. Status

- **Tested:** `nginx -t` passes. The config was exercised against a mock backend: portal loads; `verify` is hidden from the outside; no cookie or a bad cookie gives 401; a good cookie passes the user id through; forged headers are ignored.
- **Currently failing in the real stack:** the smoke checks that need a logged-in session (`/me`, `/edge-check`). They fail because of the parked IAM session bug (login works but the cookie is not accepted afterwards), **not** because of the edge. Once IAM's session lookup is fixed, those checks should pass without changing the edge.

**Checklist for the parked session bug** (login works, the cookie is then not accepted). The edge is not the cause for `/me`, since it does not use `auth_request`, but check these, in order:

1. `HTTP_COOKIE` is passed in both `fastcgi-base.conf` / `fastcgi-laravel.conf` and the `/_auth` block. Parameters are written out explicitly, so a missing one is easy to miss.
2. The `Set-Cookie` from login is reaching the browser: name (`concord_session`), `Path`, `Domain`, and no `Secure` flag over plain HTTP (a `Secure` cookie is dropped on `http://`, so `COOKIE_SECURE` must be false until HTTPS).
3. Laravel is not encrypting or re-encoding the cookie, so IAM's session lookup sees the same value it stored (check the `EncryptCookies` exceptions for `concord_session`).
4. The session lookup uses the same Redis/DB connection and key format as the login write.
5. `SESSION_DOMAIN` and `SameSite` match how the browser reaches the edge (for example `localhost` versus an IP).

Compare the cookie value in the browser with the value in `auth_sessions` or Redis; a mismatch points at the encoding step.