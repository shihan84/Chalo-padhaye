# Hostinger build: family account foundation

This branch builds only the PHP/MySQL version. It is based on the unmerged security audit PR #2 and does not modify or deploy the Vercel application.

The browser now uses same-origin `/api/auth/*` and `/api/students` endpoints. Parents can register, log in, create a first child profile and generate a 24-hour student invite. Student enrollment accepts the entire 32-character invite. Account roles come from the authenticated server response. Logout attempts server token revocation and clears local credentials. Expired sessions require a new login; no refresh-token or password-reset flow is implemented.

The existing Hostinger routing must serve the frontend at `/` and route `/api/*` to `php/api/index.php`, forwarding the Authorization header. Keep `.env`, PHP implementation, database files, tests and repository metadata outside the public document root. This PR does not configure hosting or run production migrations. Fresh databases use `database/mysql/schema.sql`; existing databases need the separately reviewed audit migration.

Trusted PHP textbook retrieval is still missing. The catalog deliberately marks sources unavailable and the tutor does not invoke a model without source material. Do not treat this as a production-ready teaching cutover. Next work: isolated public-root deployment packaging and staging verification, trusted source ingestion/retrieval, server-owned assessments and provider/auth abuse limits.

Validation: Node browser contract tests exercise empty-parent onboarding, profile creation, invite payloads and API errors; PHP/MySQL audit CI continues testing backend ownership and account lifecycle. A real hosted browser end-to-end test is still required before launch.
