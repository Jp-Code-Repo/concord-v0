1. Who is responsible for what?
IAM backend

Provides the authentication and identity API.
- Accepts login credentials and validates them.
- Creates and revokes sessions.
- Exposes the current user's identity and effective access.
- Enforces account status and authorization rules.
- Returns the appropriate responses to frontend requests.


Concord frontend

Consumes IAM's API and presents the application to the user.
- Builds the login page and submits credentials.
- Checks whether a session exists.
- Displays the authenticated user's information.
- Implements route guards and redirects.
- Displays available applications and navigation based on IAM's access data.
- Provides logout and handles expired sessions.


Edge and application services

Enforce access at the server boundary.
- Edge verifies sessions for protected service routes.
- Application services enforce authorization for their protected operations.
- Neither relies on the frontend's route guards as a security boundary.