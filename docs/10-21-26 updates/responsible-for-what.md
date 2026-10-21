---
title: Who is responsible for what?
date: 2026-10-21
tags:
  - concord/architecture
  - concord/frontend
  - concord/iam
aliases:
  - Who is responsible for what
  - responsibilities
status: working-reference
---

# Who is responsible for what?

## IAM backend

Provides the authentication and identity API.

- Accepts login credentials and validates them.
- Creates and revokes sessions.
- Exposes the current user's identity and effective access.
- Enforces account status and authorization rules.
- Returns the appropriate responses to frontend requests.

## Concord frontend

Consumes IAM's API and presents the application to the user.

- Builds the login page and submits credentials.
- Checks whether a session exists.
- Displays the authenticated user's information.
- Implements route guards and redirects.
- Displays available applications and navigation based on IAM's access data.
- Provides logout and handles expired sessions.

## Edge and application services

Enforce access at the server boundary.

- Edge verifies sessions for protected service routes.
- Application services enforce authorization for their protected operations.
- Neither relies on the frontend's route guards as a security boundary.

## Example: sign-in flow

From a discussion diagram (screenshot, 2026-10-19). It is an illustration, not an API contract.

1. The frontend collects credentials and calls `POST /api/iam/login`.
2. IAM responds with a user payload and an HttpOnly session cookie.
3. The frontend checks the session, loads identity and access, and renders the appropriate UI.

The frontend does not need to know how IAM verifies passwords, stores sessions, or calculates permissions. It only needs the API contract and to handle responses correctly. Logout works the same way: the frontend calls IAM's logout endpoint, IAM revokes the session and expires the cookie, and the frontend updates its state and redirects. The diagram says "username and password"; the real field names are unconfirmed.

## Related notes

- [[concord-frontend-working-document]]: section 4 extends this with the HRIS decisions.
- [[IAM - Access Model]]
- [[edge]]
- [[hris-decisions]]
- [[Concord Index]]
