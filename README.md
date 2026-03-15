## Authorization

Logging in doesn’t mean you’re an admin.

Every action checks permissions separately.
Admin actions (`POST`, `PATCH`, `DELETE`) require `ROLE_ADMIN` or `ROLE_TRUSTED_USER`.

If you’re logged in but don’t have the role — the API returns **403**.
