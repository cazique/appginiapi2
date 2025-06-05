**Section 6: API Versioning**

This section details the strategy for API versioning, focusing on URL-based versioning (e.g., `/api/v1/...`). It explains the importance of versioning and outlines how to manage future API iterations without breaking compatibility for existing client applications.

**6.1. Importance of API Versioning**

APIs are contracts between a service provider and its consumers. Over time, these contracts need to evolve:
*   **New Features:** New functionalities and data resources may be added.
*   **Changes to Existing Features:** The structure of responses might change (e.g., renaming fields, altering data types), endpoint behaviors could be modified, or parameters might be added or removed.
*   **Deprecation:** Certain features or endpoints might become obsolete and need to be phased out.

Attempting to introduce such changes directly into an unversioned API can break existing client applications that are built to expect the original contract. API versioning is the practice of creating distinct versions of an API, allowing these changes to be introduced in a controlled manner. Each version represents a stable contract. This approach:
*   **Ensures Backward Compatibility:** Existing client applications can continue to use an older, stable version of the API without interruption while new development targets newer versions.
*   **Provides a Clear Contract:** Clients know exactly what to expect (endpoints, request/response formats, authentication) from a specific API version they are targeting.
*   **Facilitates Smoother Upgrades:** Clients can migrate to newer API versions at their own pace when they are ready to adopt new features or adapt to breaking changes.
*   **Allows for Iteration and Improvement:** API developers can innovate and refactor the API in new versions without the fear of immediately impacting all users.

**6.2. URL-Based Versioning (`/api/v1/...`)**

There are several methods for API versioning. For this project, **URI path versioning** is the chosen method due to its clarity, explicitness, and ease of implementation.

*   **Chosen Method: URI Path Versioning**
    The API version is embedded directly into the URL path.
    *   Example for version 1: `https://yourdomain.com/api/v1/resource`
    *   Example for version 2: `https://yourdomain.com/api/v2/resource`

*   **Advantages of URI Path Versioning:**
    *   **Clear and Explicit:** The version is immediately obvious to anyone looking at the URL, including developers, testers, and clients.
    *   **Easy to Implement:** Can be implemented through directory structures on the server or straightforward routing rules in web server configurations (Apache, Nginx) or application-level routers.
    *   **Good Caching Behavior:** Different API versions have distinct URLs, which are treated as separate resources by HTTP caches (browser, proxy, CDN), leading to effective caching.
    *   **Easy Exploration and Testing:** Developers can easily access and test different API versions directly in a web browser or through tools like Postman or cURL by simply changing the URL.

*   **Alternatives (Briefly Mentioned):**
    While URI path versioning is chosen, other methods exist:
    *   **Header-based versioning:** The API version is specified in an HTTP header, often the `Accept` header (e.g., `Accept: application/vnd.yourcompany.v1+json`) or a custom header (e.g., `X-API-Version: 1`). This keeps URLs cleaner but can be less intuitive for clients, harder to test in a browser, and may have complexities with HTTP caching.
    *   **Query parameter versioning:** The version is included as a query parameter (e.g., `/api/resource?version=1`). This can clutter URLs and, like header-based versioning, might not be as cache-friendly as path-based versioning for some intermediaries.

    URI path versioning provides the best balance of clarity, ease of use, and compatibility with web infrastructure for this project.

**6.3. Implementation Strategy**

*   **Directory Structure:**
    A straightforward way to implement URI path versioning in a PHP-based API is by organizing files into versioned directories. A shared common directory can house reusable components.

    ```
    /api/
        ├── v1/                             # Root for API version 1
        │   ├── index.php                   # Entry point, router, and bootstrap for v1
        │   ├── Controllers/                # v1 specific controllers
        │   │   ├── GetController.php
        │   │   └── PostController.php
        │   │   └── ...
        │   └── Core/                       # v1 specific core logic (if changes from common)
        │       └── ValidatorV1.php         # Example: if validation rules changed for v1
        │
        ├── v2/                             # Root for API version 2 (when introduced)
        │   ├── index.php                   # Entry point, router, and bootstrap for v2
        │   ├── Controllers/                # v2 specific controllers
        │   └── Core/                       # v2 specific core logic
        │
        ├── common/                         # Shared components across versions
        │   ├── Database.php                # Database connection and interaction
        │   ├── AppGiniHelper.php           # Core AppGini integration logic
        │   ├── AuthService.php             # Authentication service (e.g., JWT handling)
        │   ├── PermissionService.php       # Permission checking logic
        │   ├── Response.php                # Standardized JSON response handling
        │   └── AbstractCrudController.php  # Base controller if applicable
        │
        └── .htaccess                       # Apache rewrite rules (if applicable)
        └── config/                         # Global configurations
            └── config.php
    ```
    *   The `index.php` within each versioned directory (`v1/`, `v2/`, etc.) acts as the front controller for that specific API version. It will initialize any version-specific configurations or services and handle routing for its version's endpoints.
    *   **Shared Code:** Core functionalities that are stable across API versions (e.g., database connection management, fundamental AppGini interaction logic via `AppGiniHelper.php`, the JWT generation/validation mechanism if it remains consistent, the `Response.php` class for formatting) should be placed in a shared directory like `common/` (or a `lib/` or `src/` directory outside the versioned API paths). This promotes code reuse and reduces duplication. Each version's `index.php` would include or autoload these shared components.

*   **Routing:**
    The web server needs to be configured to direct requests for a specific API version to the correct `index.php` file within its versioned directory.

    *   **Apache (`.htaccess` placed in the `/api/` directory):**
        ```apacheconf
        RewriteEngine On
        RewriteBase /api/

        # Prevent direct access to common or config directories if they are under web root
        RewriteRule ^common/ - [F]
        RewriteRule ^config/ - [F]

        # Route /api/v1/* requests to /api/v1/index.php
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^v1/(.*)$ v1/index.php?_url=/$1 [QSA,L]

        # Example for when v2 is added:
        # RewriteCond %{REQUEST_FILENAME} !-f
        # RewriteCond %{REQUEST_FILENAME} !-d
        # RewriteRule ^v2/(.*)$ v2/index.php?_url=/$1 [QSA,L]
        ```
        The `_url` parameter can then be used by the `index.php` in `v1/` (or `v2/`) for internal routing.

    *   **Nginx (within the `server` block of your Nginx site configuration):**
        ```nginx
        location /api/ {
            # Route /api/v1/* requests
            location /api/v1/ {
                try_files $uri $uri/ /api/v1/index.php?$args;
            }

            # Example for when v2 is added:
            # location /api/v2/ {
            #    try_files $uri $uri/ /api/v2/index.php?$args;
            # }

            # Prevent direct access to common or config if structured under /api/
            location /api/common/ { deny all; }
            location /api/config/ { deny all; }
        }
        ```
    The `index.php` in each versioned directory would then typically use a simple router (or parse `$_GET['_url']` or `$_SERVER['REQUEST_URI']`) to map the remainder of the path to the appropriate controller and action for that version.

**6.4. Managing Future Versions (e.g., `/api/v2/`)**

*   **When to Create a New Version:**
    A new API version (e.g., `v2`) should be introduced primarily when making **backward-incompatible (breaking) changes**. These include:
    *   Changing the data type of a field in a response (e.g., integer to string).
    *   Removing a field from a response that clients might depend on.
    *   Renaming fields in a response or request.
    *   Changing endpoint paths or HTTP methods for existing functionality.
    *   Introducing fundamentally new authentication or authorization mechanisms that affect all or most endpoints.
    *   Altering required parameters for an existing endpoint.

    Non-breaking changes, such as adding new optional fields to responses, adding new endpoints, or introducing new optional request parameters, generally do *not* require a new API version. These can usually be incorporated into the existing version.

*   **Process for Introducing v2 while v1 is Live:**
    1.  **Plan v2:** Clearly define the scope of changes for the new version.
    2.  **Develop v2 in Parallel:**
        *   Create the new versioned directory structure (e.g., `/api/v2/`).
        *   Copy relevant components (controllers, version-specific core logic) from `/api/v1/` to serve as a starting point for `v2/`.
    3.  **Isolate Breaking Changes:** Implement all breaking changes exclusively within the `v2/` codebase. The `v1/` codebase should remain untouched to ensure continued stability for existing clients.
    4.  **Maximize Shared Code:** Leverage the `common/` directory as much as possible for logic that remains consistent between `v1` and `v2`. If a shared component itself needs a breaking change for `v2`, you might need to:
        *   Duplicate and modify it within the `v2/core/` directory.
        *   Or, for more complex shared libraries, consider internal versioning or conditional logic within the shared code (though this can increase complexity).
    5.  **Independent Testing:** Thoroughly test `v2` as a standalone API, including all its endpoints, authentication, authorization, and error handling.
    6.  **Update Documentation:** Create or update API documentation to clearly distinguish between `v1` and `v2` endpoints, request/response formats, and behaviors. Mark `v1` endpoints as "legacy," "older," or "maintenance-only" if applicable.
    7.  **Client Communication:** Announce the availability of `v2` to API consumers. Provide clear migration guides detailing the changes from `v1` and the benefits of upgrading. If `v1` is planned for deprecation, communicate this timeline clearly.
    8.  **Support Both Versions:** Maintain support for `v1` for a reasonable period to allow clients ample time to migrate. This period depends on the user base and the criticality of the API.
    9.  **Deprecation of v1 (Eventually):**
        *   Monitor the usage of `v1` endpoints.
        *   Send reminders about the upcoming `v1` sunset date.
        *   Once usage is negligible or the sunset date is reached, `v1` can be officially retired. This might involve:
            *   Removing the `/api/v1/` code and routes.
            *   Configuring `v1` endpoints to return specific "deprecated" error responses (e.g., HTTP `410 Gone` or a custom error message).

**6.5. Default Version (Optional)**

Some API providers choose to route requests made to a non-versioned base URL (e.g., `/api/resource`) to their latest or default stable version (e.g., redirecting or internally routing to `/api/v1/resource`).
*   **Convenience vs. Risk:** While this can be convenient for new clients, it carries the risk that if the default version changes to one with breaking updates, it could inadvertently break clients that were not explicitly targeting a version.
*   **Recommendation for This Project:** For maximum clarity and stability, it is recommended that clients be required to **explicitly specify the API version in the URL** (e.g., `/api/v1/...`). This project will adopt the explicit versioning standard, avoiding a default non-versioned path.

By implementing URL-based versioning as described, the API can evolve gracefully. This strategy provides stability for existing consumers by ensuring their integrations continue to work, while simultaneously allowing for the introduction of new features and improvements in future API versions.
