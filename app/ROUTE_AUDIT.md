# Route Audit

This document enumerates all routes discovered in the Symfony application, grouped by feature area, and highlights missing or risky definitions.

===========================
 AUTH ROUTES
===========================
METHOD  PATH                     CONTROLLER / HANDLER
POST    /api/login               Security json_login (LexikJWTAuthenticationBundle success/failure handlers)
POST    /api/token/refresh       gesdinet/jwt-refresh-token-bundle refresh_jwt
POST    /api/register            RegisterController::__invoke
GET     /api/me                  MeController::__invoke

- INPUT
  - `/api/login`: JSON `{ "email": "user@example.com", "password": "secret" }`.
  - `/api/token/refresh`: JSON `{ "refresh_token": "<refresh_token>" }` per bundle defaults.
  - `/api/register`: JSON `{ "email": "new.user@example.com", "password": "SecurePass123!" }` (both required).
  - `/api/me`: No body; uses authenticated JWT user.
- OUTPUT
  - `/api/login`: JWT response from Lexik success handler.
  - `/api/token/refresh`: New access token and refresh token payload from bundle.
  - `/api/register`: `{ "status": "ok" }` on success; conflicts or validation errors return `{ "error": "..." }` with HTTP 400/409.
  - `/api/me`: `{ "id": <int>, "email": <string>, "roles": ["ROLE_USER", ...] }` or `{ "error": "Unauthorized" }` (401).
- AUTH
  - `/api/login`, `/api/register`, `/api/token/refresh`, `/api/docs*` are PUBLIC_ACCESS. All other `/api/**` routes require a valid JWT (`IS_AUTHENTICATED_FULLY`).【F:config/packages/security.yaml†L10-L36】

===========================
 USER ROUTES
===========================
METHOD  PATH                     CONTROLLER
POST    /api/users               UserController::createOrUpdate
GET     /api/users/{id}          UserController::getUser

- PARAMS
  - `{id}`: integer (no explicit requirement set in the route definition).
- INPUT
  - POST `/api/users`: JSON with required `externalId`; optional `displayName`, `city`, `country`, `timezone`.
- OUTPUT
  - Returns user profile fields (`id`, `externalId`, `displayName`, `city`, `country`, `timezone`, `createdAt`). 400 for validation errors; 500 on server errors.
- AUTH: JWT required (not public).

===========================
 REQUEST ROUTES
===========================
METHOD  PATH                          CONTROLLER
POST    /api/requests                 RequestController::create
GET     /api/requests/{id}            RequestController::getRequest
GET     /api/requests/mine            RequestController::listMine
GET     /api/requests/{id}/matches    RequestController::getMatches

- PARAMS
  - `{id}`: integer (explicit `\\d+` requirement for `/api/requests/{id}`, but no regex on `{id}` in `/matches`).
  - Query: `offset` and `limit` for `/mine`; `limit` for `/matches` (default 20, max 100 implied in docs).
- INPUT
  - POST `/api/requests`: JSON with required `rawText`, `type`; optional `city`, `country`; legacy `ownerId` must match JWT user if provided.
- OUTPUT
  - Create: returns request data (`id`, `ownerId`, `type`, `city`, `country`, `status`, `createdAt`, `rawText`); 400 on validation issues; 404 if owner missing.
  - Get: returns request data or 404.
  - Mine: array of current user’s active requests with the same fields.
  - Matches: array of matched requests with metadata; 404 if base request missing.
- AUTH: JWT required for all.

===========================
 MATCHES / RECOMMENDATIONS
===========================
Covered by `GET /api/requests/{id}/matches` (see Request routes). No separate recommendation endpoints exist.

===========================
 CHAT ROUTES
===========================
METHOD  PATH                                         CONTROLLER
POST    /api/chats/{userId}/start                    ChatController::startChat
GET     /api/chats                                   ChatController::listChats
GET     /api/chats/{chatId}/messages                 ChatController::listMessages
POST    /api/chats/{chatId}/messages                 ChatController::sendMessage
POST    /api/chats/{chatId}/messages/{messageId}/read ChatController::markRead

- PARAMS
  - `{userId}`, `{chatId}`, `{messageId}`: expected integers (no regex requirements defined).
  - Query: `offset` (default 0) and `limit` (default 50, capped at 200) for listing messages.
- INPUT
  - Start chat: no body; path provides the other participant ID.
  - List chats/messages: no body; query for pagination on messages.
  - Send message: JSON `{ "content": "Hello there!" }` (required `content`).
  - Mark read: no body.
- OUTPUT
  - Start chat: chat summary with participants, lastMessage, unreadCount.
  - List chats: array of chat summaries for current user.
  - List messages: array of messages (`id`, `chatId`, `senderId`, `content`, `createdAt`, `isRead`).
  - Send message: created message JSON with the same fields (HTTP 201) or validation errors.
  - Mark read: `{ "status": "ok" }` or errors (400/401/403/404).
- AUTH: JWT required for all chat endpoints.

===========================
 FEEDBACK ROUTES
===========================
METHOD  PATH                    CONTROLLER
POST    /api/feedback/app       AppFeedbackController::submit
POST    /api/feedback/match     MatchFeedbackController::submit

- INPUT
  - App feedback: JSON with required `userId`, `rating`; optional `mainIssue`, `comment`.
  - Match feedback: JSON with required `userId`, `relevanceScore`; optional `matchId`, `targetRequestId`, `reasonCode`, `comment`, `mainIssue`.
- OUTPUT
  - Both return `{ "status": "ok" }` (HTTP 201) or `{ "error": "..." }` on validation errors.
- AUTH: Under `/api/**`, so JWT required by firewall, but controllers accept `userId` from the payload instead of deriving it from JWT (see Security notes).

===========================
 MISC / SYSTEM ROUTES
===========================
METHOD  PATH             CONTROLLER
GET     /api/docs        nelmio_api_doc.controller.swagger_ui
GET     /api/docs.json   nelmio_api_doc.controller.swagger
(GET)   /_error/*        Error pages (dev only)

===========================
 MISSING OR FRONTEND-ONLY ROUTES
===========================
No references found in the repository for `/api/requests/recommendations` or `/api/requests/incoming`. `/api/requests/mine` is implemented. (No frontend/bot code in this repo references missing URLs.)

===========================
 SECURITY NOTES
===========================
- Feedback endpoints rely on a `userId` in the request body and do not cross-check it with the authenticated JWT user. This allows user spoofing if JWT is compromised or misconfigured. Consider deriving the user ID from the token and ignoring body-provided IDs.【F:src/Controller/Feedback/AppFeedbackController.php†L15-L70】【F:src/Controller/Feedback/MatchFeedbackController.php†L15-L82】
- `POST /api/requests` accepts a legacy `ownerId` field; although the controller comments say it must match the authenticated user, validation happens in the service. Explicitly compare against the JWT user in the controller or remove the field to avoid spoofing.【F:src/Controller/Api/RequestController.php†L21-L90】
- Several chat route parameters (`userId`, `chatId`, `messageId`) lack regex constraints, meaning non-numeric strings could hit the route before type casting fails. Add `requirements` to tighten routing and reduce accidental matches.【F:src/Controller/Api/ChatController.php†L31-L191】
- `/api/requests/{id}/matches` does not declare an `id` regex requirement, so it can match non-numeric paths that bypass the stricter `/api/requests/{id}` route. Add `requirements: ['id' => '\\d+']` for consistency.【F:src/Controller/Api/RequestController.php†L196-L246】
- Authentication: only `/api/login`, `/api/register`, `/api/token/refresh`, and docs endpoints are marked PUBLIC_ACCESS; all other `/api/**` routes require JWT. Ensure clients send the `Authorization: Bearer <token>` header.

===========================
 NORMALIZATION SUGGESTIONS
===========================
- Align parameter constraints across request routes by adding `requirements: ['id' => '\\d+']` to `/api/requests/{id}/matches` and chat/message IDs for consistency and to prevent overly broad matches.
- Drop the `ownerId` and `userId` body fields in favor of JWT-derived user identity to avoid spoofing and simplify clients.
- If future recommendation endpoints are added, follow a consistent pattern such as `/api/requests/{id}/recommendations` to mirror `/api/requests/{id}/matches`.
