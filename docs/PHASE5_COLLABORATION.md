# Phase 5 — Project activity and in-app collaboration

Phase 5 adds a **durable, project-authorized activity feed** and member-scoped in-app notifications. The React workspace includes an **Activity & alerts** view. When WebSocket configuration is absent or unavailable, it continues fetching the authorized API periodically; it is not dependent on realtime infrastructure for task correctness.

## Backend changes

- Task create/update/delete observers write compact \`project_activities\` entries (project, actor, task ID, action, task title) alongside the older internal \`activity_logs\` records.
- Project owners and current members except the actor receive database-backed \`project_notifications\`. Notifications are **in-app only**; the feature does not queue or send notification emails.
- \`GET /api/v2/projects/{project}/activity\` requires the Laravel project \`view\` policy and returns the latest 20 records (maximum 50 per page).
- \`GET /api/v2/notifications\` returns an access-filtered, paginated list plus \`unread_count\`.
- \`PATCH /api/v2/notifications/{notification}/read\` and \`PATCH /api/v2/notifications/read-all\` update only the calling user's still-accessible notifications.
- Removed project members cannot retrieve historical activity or old alerts via the API.
- Activity records follow project deletion through an FK cascade; task history retains task ID after task deletion.

## Broadcasting and privacy

\`ProjectActivityChanged\` uses \`private-project.{id}\`, the existing server-side \`project.{projectId}\` authorization callback, and Laravel's after-commit event contract. The broadcast payload is **only** \`project_id\` and \`activity_id\`—it does not contain task descriptions, user emails, invitation tokens, or membership data.

The former \`TaskUpdated\` event remains available for existing in-process consumers, but no longer broadcasts the entire Task model. React listeners refetch the scoped API to obtain the current truth rather than trusting a socket payload. **Authorization applies again on every API read.**

## Local operation

The existing \`.env.example\` sets \`BROADCAST_CONNECTION=log\`, which does **not** deliver actual WebSocket messages. Without configured Reverb credentials the app uses a bounded 45-second polling fallback and does not open a socket connection.

To enable actual sockets, install/configure an appropriate Pusher-compatible Laravel Reverb server separately, then configure \`REVERB_APP_ID\`, \`REVERB_APP_KEY\`, \`REVERB_APP_SECRET\`, \`REVERB_HOST\`, \`REVERB_PORT\`, \`REVERB_SCHEME\` and corresponding \`VITE_REVERB_*\` public browser settings. Use \`BROADCAST_CONNECTION=reverb\` with a supervised broadcasting queue worker, trusted TLS and properly restricted origins. Never expose the Reverb secret in \`VITE_\` variables.

The current project does **not** include \`laravel/reverb\` in \`composer.json\`; real Reverb deployment still needs a separately reviewed dependency/install and infrastructure change. This PR adds safe application integration, **not a claim that a production WebSocket cluster is running**.

## Tests and release limits

- Laravel tests cover project isolation, owner/member notification delivery, actor exclusion, unread/read-all, revoked membership and event payload minimization.
- React tests cover the activity view and marking a notification read.
- All existing Chromium E2E, TypeScript, Vitest, PHP lint, MySQL migrations, Laravel, Sail smoke, queue and scheduler checks remain part of the existing consolidated CI.
- Test against concurrent team membership revocation, long-lived socket connections, worker restarts, queue retries and privacy/security in staging before enabling Reverb publicly.
- Activity uses compact history to avoid sending sensitive task descriptions; this is **not** an immutable compliance audit log.

No external emails, scheduled email campaigns or live production notifications are triggered by these changes.
