**Section 5: CORS and HTTP Headers Configuration**

This section details the necessary configurations for Cross-Origin Resource Sharing (CORS) to allow web applications from different domains to access the API. It also covers the enforcement of `Content-Type: application/json` for all relevant API responses and briefly touches upon other beneficial security headers.

**5.1. Understanding CORS (Cross-Origin Resource Sharing)**

*   **Why CORS is Necessary:**
    For security reasons, web browsers implement a "same-origin policy." This policy restricts how a document or script loaded from one origin (defined by protocol, domain, and port) can interact with resources from another origin. By default, cross-origin HTTP requests initiated from scripts (e.g., using `XMLHttpRequest` or the Fetch API) are blocked by the browser unless the server at the other origin explicitly permits them.
    CORS is a mechanism that uses additional HTTP headers to tell browsers to give a web application running at one origin, access to selected resources on a different origin. APIs intended to be consumed by web frontends hosted on different domains (e.g., a React SPA on `https://my-app.com` consuming an API on `https://api.my-app.com`) *must* implement CORS.

*   **Preflight Requests (`OPTIONS`):**
    For certain types of cross-origin requests, known as "non-simple" requests, the browser automatically sends a preliminary HTTP request called a "preflight request" using the `OPTIONS` method. Non-simple requests include those that:
    *   Use methods other than `GET`, `HEAD`, or `POST` (e.g., `PUT`, `DELETE`, `PATCH`).
    *   Use `POST` with a `Content-Type` other than `application/x-www-form-urlencoded`, `multipart/form-data`, or `text/plain`.
    *   Include custom headers (e.g., `Authorization` for JWTs, `X-Requested-With`).
    The server must respond to this `OPTIONS` request with appropriate CORS headers, indicating whether the actual request is permitted. If the preflight is successful, the browser then sends the actual request.

**5.2. CORS Configuration in `api/index.php` or Response Class**

CORS headers should be set on the server-side for every relevant response. This logic is typically placed early in the request lifecycle, often in the main entry point (`api/v1/index.php`) or within a centralized response mechanism or middleware.

*   **Headers to Set:**
    *   `Access-Control-Allow-Origin`: This is the most critical CORS header. It specifies which origins are permitted to access the resource.
        *   **Development/Testing:** `Access-Control-Allow-Origin: *` (allows any origin). This is convenient for development but generally unsafe for production.
        *   **Production (Recommended):** List specific, trusted domains. For example, `Access-Control-Allow-Origin: https://your-frontend-app.com`.
        *   **Multiple Origins:** If you need to allow multiple specific origins, the server must dynamically check the `Origin` header from the incoming request against a whitelist of allowed origins. If the request's `Origin` is in the whitelist, then `Access-Control-Allow-Origin` should be set to that specific origin value. You cannot list multiple domains directly in the `Access-Control-Allow-Origin` header value itself (unless using `*`).
        *   **Credentials:** If `Access-Control-Allow-Credentials` is set to `true`, `Access-Control-Allow-Origin` *cannot* be `*`. It must be a specific origin.
    *   `Access-Control-Allow-Methods`: Specifies which HTTP methods are allowed when accessing the resource (e.g., `GET, POST, PUT, PATCH, DELETE, OPTIONS`). This should list all methods the API intends to support for cross-origin requests.
    *   `Access-Control-Allow-Headers`: Specifies which HTTP request headers are allowed to be sent by the client. This is crucial for requests that include headers like `Content-Type` (e.g., for JSON request bodies), `Authorization` (for JWTs or other token-based auth), and common library headers like `X-Requested-With`.
    *   `Access-Control-Allow-Credentials` (Optional): A boolean (`true` or `false`). This header indicates whether the browser should include credentials (such as cookies, HTTP authentication, or TLS client certificates) with the cross-origin request. If set to `true`, the client-side script must also explicitly enable credentials in its request (e.g., `xhr.withCredentials = true` or `fetch({ credentials: 'include' })`). As mentioned, if this is `true`, `Access-Control-Allow-Origin` must be a specific domain.
    *   `Access-Control-Max-Age` (Optional): Specifies how long (in seconds) the results of a preflight request (`OPTIONS`) can be cached by the browser. This can reduce the number of preflight requests. Example: `Access-Control-Max-Age: 86400` (caches for 1 day).

*   **Handling Preflight (`OPTIONS`) Requests:**
    The API must be configured to correctly handle `OPTIONS` requests:
    1.  Detect if the incoming request method is `OPTIONS`.
    2.  If it is, the server should respond with the necessary `Access-Control-Allow-*` headers (as listed above).
    3.  The server should then send an HTTP `204 No Content` or `200 OK` status. `204 No Content` is often preferred as it indicates success without a body.
    4.  Crucially, the script execution should terminate immediately after sending the preflight response. It should *not* proceed to API routing, authentication, or controller logic.

**5.3. Example Implementation (Conceptual PHP)**

This logic is best placed at the beginning of the request processing pipeline, such as in `api/v1/index.php` or a dedicated bootstrap file loaded by it.

*   **In `api/v1/index.php` (or a global bootstrap/middleware file):**

    ```php
    <?php
    // File: api/v1/index.php (or a bootstrap file like api/bootstrap.php)

    // Load configuration (e.g., from config/config.php)
    // $appConfig = require __DIR__ . '/../config/config.php';
    // $corsConfig = $appConfig['cors'] ?? [];

    // // --- Default CORS Configuration (fallback if not in config) ---
    // $allowedOriginsConfig = $corsConfig['allowed_origins'] ?? ['*']; // Default to all for simplicity if not configured
    // $allowedMethodsConfig = $corsConfig['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    // $allowedHeadersConfig = $corsConfig['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'Cache-Control'];
    // $supportsCredentialsConfig = $corsConfig['supports_credentials'] ?? false;
    // $maxAgeConfig = $corsConfig['max_age'] ?? null; // e.g., 86400 for 1 day

    // // --- Dynamic Origin Handling ---
    // $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // $effectiveAllowedOrigin = null;

    // if (is_array($allowedOriginsConfig)) {
    //     if (in_array($requestOrigin, $allowedOriginsConfig)) {
    //         $effectiveAllowedOrigin = $requestOrigin;
    //     } elseif (in_array('*', $allowedOriginsConfig) && !$supportsCredentialsConfig) {
    //         // Allow '*' only if credentials are not supported
    //         $effectiveAllowedOrigin = '*';
    //     }
    //     // If $requestOrigin is empty (e.g. server-to-server, some tools, or older browsers),
    //     // and '*' is in allowed list, it might be set. Otherwise, no ACAO header.
    // } elseif (is_string($allowedOriginsConfig)) { // Single string like '*' or 'https://domain.com'
    //     if ($allowedOriginsConfig === '*' && $supportsCredentialsConfig) {
    //         // Invalid: '*' cannot be used with credentials. Log an error, don't set.
    //         error_log("CORS Misconfiguration: Cannot use '*' origin with credentials true.");
    //     } else {
    //         $effectiveAllowedOrigin = $allowedOriginsConfig;
    //     }
    // }

    // if ($effectiveAllowedOrigin) {
    //     header("Access-Control-Allow-Origin: {$effectiveAllowedOrigin}");
    // }
    // // If $effectiveAllowedOrigin is null, no ACAO header is sent, and browser will likely block.

    // header("Access-Control-Allow-Methods: " . implode(', ', $allowedMethodsConfig));
    // header("Access-Control-Allow-Headers: " . implode(', ', $allowedHeadersConfig));

    // if ($supportsCredentialsConfig) {
    //     header("Access-Control-Allow-Credentials: true");
    // }

    // if ($maxAgeConfig !== null) {
    //     header("Access-Control-Max-Age: {$maxAgeConfig}");
    // }

    // // --- Handle OPTIONS Preflight Requests ---
    // if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    //     // All necessary CORS headers have been set above.
    //     // Respond with 204 No Content (or 200 OK for some legacy clients/browsers).
    //     http_response_code(204);
    //     exit; // Terminate script execution for OPTIONS requests.
    // }

    // // ... rest of your API logic (e.g., Response class definition, routing, authentication, controllers) ...
    // // For example, ensure Content-Type is set for actual responses later:
    // // if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
    // // This is better handled in the Response::json() method itself.
    ?>
    ```

*   **Integration into `Response` class (conceptual for `Content-Type`):**
    The `Response::json()` method from Section 4 is the ideal place to ensure `Content-Type: application/json` is always set for JSON responses. CORS headers, however, are better handled globally and earlier, especially the `OPTIONS` request handling.

    ```php
    <?php
    // // In api/core/Response.php (from Section 4, augmented for clarity)
    // class Response {
    //     public static function json($data, int $statusCode = 200, array $headers = []) {
    //         if (!headers_sent()) {
    //             header_remove(); // Good practice
    //             http_response_code($statusCode);

    //             // CRITICAL: Set Content-Type for JSON responses
    //             header('Content-Type: application/json; charset=utf-8');

    //             // Note: CORS headers are ideally set globally before this point,
    //             // especially Access-Control-Allow-Origin, as they apply to preflight
    //             // requests too, which don't typically go through this json() method.
    //             // However, other response-specific headers can be added here.
    //             foreach ($headers as $headerName => $headerValue) {
    //                 header("{$headerName}: {$headerValue}");
    //             }
    //         }
    //         // ... rest of the JSON encoding and output logic from Section 4 ...
    //         echo json_encode(/* ... */);
    //         exit;
    //     }
    //     // ... other methods ...
    // }
    ?>
    ```

*   **Configuration for CORS (in `config/config.php`):**
    It's crucial to make CORS settings configurable, especially `allowed_origins`.

    ```php
    <?php
    // // In config/config.php
    // return [
    //     'environment' => 'production', // 'development' or 'production'
    //     'cors' => [
    //         // For production: specific domains
    //         'allowed_origins' => ['https://your-frontend.com', 'https://admin.your-frontend.com'],
    //         // For development, you might use:
    //         // 'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000', '*'],
    //         'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    //         'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-API-KEY', 'Cache-Control'],
    //         'supports_credentials' => false, // Set to true if cookies/session tokens are needed
    //         'max_age' => 86400, // Cache preflight for 1 day (optional)
    //     ],
    //     // ... other configurations (database, JWT, etc.)
    // ];
    ?>
    ```

**5.4. Enforcing `Content-Type: application/json`**

*   As demonstrated in the conceptual `Response::json()` method (Section 4.3 and above), the header `header('Content-Type: application/json; charset=utf-8');` must be set for *all* API responses that return a JSON body.
*   This ensures that clients (browsers, mobile apps, other services) correctly interpret the response body as JSON data.
*   For responses that do not have a body (e.g., `204 No Content` from a `DELETE` request or an `OPTIONS` preflight), a `Content-Type` header is not strictly necessary, but setting it for all responses (except perhaps `204`) via a central response mechanism is generally not harmful. The key is that if there *is* a body, its type must be accurately declared.

**5.5. Other Security Headers (Brief Mention)**

While CORS headers are essential for cross-origin functionality and `Content-Type` for data interpretation, other HTTP headers can significantly enhance the security of the API and the web applications consuming it. These should also be considered for global implementation (e.g., in `index.php` or via web server configuration like Nginx/Apache).

*   `Strict-Transport-Security (HSTS)`: Instructs browsers to only communicate with the server over HTTPS.
    *   Example: `Strict-Transport-Security: max-age=31536000; includeSubDomains`
*   `X-Content-Type-Options: nosniff`: Prevents browsers from MIME-sniffing the content type away from the declared `Content-Type`. This is a security measure against certain types of attacks.
*   `X-Frame-Options: DENY` (or `SAMEORIGIN`): Helps protect against clickjacking attacks by controlling whether the API responses can be embedded in `<frame>`, `<iframe>`, or `<object>` tags.
*   `Content-Security-Policy (CSP)`: A powerful header that helps prevent Cross-Site Scripting (XSS) and other injection attacks by defining which sources of content are allowed to be loaded by the browser. CSP is complex and requires careful configuration based on the application's needs.

Implementing these headers, alongside proper CORS and Content-Type configuration, contributes to a more secure and robust API. The specifics of these additional security headers are often tailored to the application and deployment environment.

This section provides a solid foundation for configuring essential HTTP headers, enabling secure cross-origin communication and ensuring clients correctly understand API responses.
