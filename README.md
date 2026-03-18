## Authorization

Logging in doesn’t mean you’re an admin.

Every action checks permissions separately.
Admin actions (`POST`, `PATCH`, `DELETE`) require `ROLE_ADMIN` or `ROLE_TRUSTED_USER`.

ROLE_TRUSTED_USER: can perform admin actions only on `notebook` products. Attempts on other categories return **403**

If you’re logged in but don’t have the role — the API returns **403**.
