## Authorization

- Logging in doesn’t mean you’re an admin.
- Every action checks permissions separately.
- Login is via **`Google OAuth`** provider.
- After successful login, a JWT token is returned in the URL `(#token=<jwt>)`
- The token must be stored in localStorage and sent in requests as: `Authorization: Bearer <jwt>`
- Admin actions (`POST`, `PATCH`, `DELETE`) require `ROLE_ADMIN` or `ROLE_TRUSTED_USER`.
- ROLE_TRUSTED_USER: can perform admin actions only on `notebook` products. Attempts on other categories return **403**
- If you’re logged in but don’t have the proper role, the API returns **403**.
- If you are not authenticated, the API returns **401**.
- Export products to XLSX (public access)

