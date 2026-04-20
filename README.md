## Authorization

- Logging in doesn’t mean you’re an admin.
- Every action checks permissions separately.
- Login is via **`Google OAuth`** provider.
- After successful login, a JWT token is issued and stored in `HttpOnly cookies` (access + refresh flow).
- Access token lifetime: **5 minutes**
- Refresh token lifetime: **1 hour**
- Access token is automatically refreshed via `/api/refresh` when expired.
- If refresh token is expired or missing, the user must log in again.
- 
## Roles and permissions
- `ROLE_ADMIN` or `ROLE_TRUSTED_USER` can perform admin actions (`POST`, `PATCH`, `DELETE`).
- ROLE_TRUSTED_USER: can perform admin actions only in the `notebook` category. Attempts on other categories return **403**
- ROLE_TRUSTED_USER can **PATCH / DELETE only their own products**. Attempts to modify products owned by other users return **403**
- If you’re logged in but don’t have the proper role, the API returns **403**.
- If you are not authenticated, the API returns **401**
- Export products to XLSX (public access)

