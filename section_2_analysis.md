**Section 2: Authentication and Authorization**

This section details the implementation of JSON Web Tokens (JWT) for securing API endpoints, outlines the JWT validation process (ideally as middleware), and explains how to integrate with AppGini's native user roles and permissions for authorizing API requests.

**2.1. Token-Based Authentication: JWT (JSON Web Tokens)**

*   **Rationale for JWT:**
    JSON Web Tokens (JWT) are chosen as the primary authentication mechanism for this API proposal due to several advantages:
    *   **Stateless:** JWTs are self-contained, meaning the server does not need to store session state. The token itself contains all necessary information for verification. This is highly beneficial for scalability.
    *   **Widely Supported:** JWT is an open standard (RFC 7519) with libraries available in virtually all programming languages, making it easy to integrate with various clients (web, mobile, other services).
    *   **Good for APIs:** It's a common and effective method for securing APIs due to its stateless nature and ability to be easily transmitted in HTTP headers.
    *   **Mobile Clients:** Well-suited for mobile applications which may not maintain persistent sessions like web browsers.

    While OAuth2 is a more comprehensive framework for authorization and can also use JWTs, for the initial scope of providing a secure API for AppGini data, JWTs directly offer a simpler and more direct path to token-based authentication. OAuth2 could be considered for more complex scenarios like third-party application access in the future.

*   **JWT Structure:**
    A JWT consists of three parts, separated by dots (`.`):
    1.  **Header:** Contains metadata about the token, typically the token type (`JWT`) and the signing algorithm used (e.g., `HS256` - HMAC SHA256). This part is Base64Url encoded.
        `{"alg": "HS256", "typ": "JWT"}`
    2.  **Payload:** Contains the claims. Claims are statements about an entity (typically, the user) and additional data. Common claims include `iss` (issuer), `exp` (expiration time), `sub` (subject), `aud` (audience), as well as custom claims like `user_id`, `username`, `group_id`. This part is also Base64Url encoded.
        `{"user_id": "admin", "username": "Administrator", "group_id": "2", "exp": 1678886400}`
    3.  **Signature:** To verify the token's integrity, the header, payload, and a secret key are signed using the algorithm specified in the header. If the token is tampered with or the signature is incorrect, it will be rejected.
        `HMACSHA256(base64UrlEncode(header) + "." + base64UrlEncode(payload), secret_key)`

*   **Key Secret:**
    The security of JWTs relies heavily on the secrecy of the key used to sign the tokens. This **secret key** must be strong, unique, and kept confidential. It should never be hardcoded into the application. Instead, it must be stored securely, typically in an environment variable or a `.env` file that is not committed to version control. For HS256, this is a symmetric key known only to the server.

*   **Recommended Library:**
    For handling JWT operations in PHP, a robust and well-maintained library is essential. `firebase/php-jwt` is a popular and widely used choice. It provides functionalities for encoding (creating), decoding (verifying), and handling various aspects of JWTs, including exceptions for expired or invalid tokens. Installation via Composer: `composer require firebase/php-jwt`.

**2.2. Authentication Endpoint (`/api/v1/login`)**

*   **Purpose:**
    This endpoint is responsible for authenticating users. Users will send their AppGini credentials (username and password) to `/api/v1/login` to obtain a JWT if the credentials are valid.

*   **Process:**
    1.  The endpoint receives `username` and `password` via a POST request, typically in a JSON body.
    2.  The received credentials must be validated against AppGini's `membership_users` table. This involves:
        *   Fetching the user record based on the `username` (case-insensitive, as AppGini often does).
        *   Comparing the provided `password` with the stored password hash. AppGini typically uses MD5 for password hashing (field `passMD5`). If AppGini has been customized to use `password_hash()` and `password_verify()`, that mechanism should be used instead.
        *   Example AppGini-style password check:
            ```php
            // Assuming $username and $password are provided
            // $db = new Database(); // Your database connection
            // $safeUsername = strtolower(makeSafe($username)); // makeSafe is an AppGini function
            // $storedPasswordHash = sqlValue("SELECT passMD5 FROM membership_users WHERE LCASE(memberID)='{$safeUsername}'");
            // if ($storedPasswordHash && md5($password) === $storedPasswordHash) { /* Valid */ }
            ```
            It's crucial to use AppGini's `makeSafe()` or equivalent PDO parameterized queries to prevent SQL injection when fetching user data.
    3.  If the credentials are valid, a JWT is generated.
    4.  **JWT Payload:** The payload should contain essential, non-sensitive user information for authorization and context. Standard claims like `iat` (issued at), `exp` (expiration time), `iss` (issuer), `aud` (audience) should be included. Custom claims relevant to AppGini would be:
        *   `user_id`: AppGini's `memberID` (primary key from `membership_users`).
        *   `username`: The AppGini `memberID` (username).
        *   `group_id`: The `groupID` from `membership_users`, linking to `membership_groups`.
        *   Optionally, a `jti` (JWT ID) can be included for unique token identification, which can be useful for implementing token blacklisting mechanisms if needed (though this adds complexity).
    5.  The generated JWT is returned in a JSON response, along with its expiration time, to the client.

*   **Example (Conceptual PHP for `AuthController.php`):**
    This example assumes the new directory structure and services proposed in Section 1.

    ```php
    <?php
    // File: api/v1/Controllers/AuthController.php (Conceptual)

    // Ensure this is not directly accessible via web URL if not through index.php router
    // defined('APP_ENTRY_POINT') or die('Access denied');

    require_once __DIR__ . '/../../../core/AppGiniHelper.php'; // For AppGini user functions
    require_once __DIR__ . '/../../../core/Response.php';
    require_once __DIR__ . '/../../../vendor/autoload.php'; // For firebase/php-jwt

    use Firebase\JWT\JWT;

    class AuthController {
        private $config;
        private $db; // Assuming DB connection is passed or available via a service

        public function __construct($config, $db) {
            $this->config = $config;
            $this->db = $db; // Or use a Database service
        }

        public function login() {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['username']) || !isset($data['password'])) {
                Response::json(['error' => true, 'message' => 'Username and password required.'], 400);
                return;
            }

            $username = $data['username'];
            $password = $data['password'];

            // 1. Validate credentials against AppGini's users table
            // Note: AppGiniHelper::validateUser needs to be implemented robustly.
            // It should handle password verification as AppGini does (e.g., md5($password) === passMD5)
            // and use makeSafe() or prepared statements.
            $userInfo = AppGiniHelper::validateUser($this->db, $username, $password);

            if ($userInfo && isset($userInfo['memberID']) && isset($userInfo['groupID'])) {
                $issuedAt = time();
                // Expiration time from config (e.g., 3600 seconds for 1 hour)
                $expirationTime = $issuedAt + $this->config['jwt']['expiration_time'];

                $payload = [
                    'iat' => $issuedAt,
                    'exp' => $expirationTime,
                    'iss' => $this->config['jwt']['issuer'],     // e.g., 'https://yourdomain.com'
                    'aud' => $this->config['jwt']['audience'],   // e.g., 'https://yourdomain.com/api'
                    'user_id' => $userInfo['memberID'],          // AppGini's memberID
                    'username' => $username,                     // AppGini's username (memberID)
                    'group_id' => $userInfo['groupID']           // AppGini's groupID
                    // 'jti' => bin2hex(random_bytes(16))       // Optional: JWT ID for blacklisting
                ];

                $jwtSecretKey = $this->config['jwt']['secret_key'];
                if (empty($jwtSecretKey)) {
                    error_log("JWT secret key is not configured.");
                    Response::json(['error' => true, 'message' => 'Authentication server configuration error.'], 500);
                    return;
                }

                $jwt = JWT::encode($payload, $jwtSecretKey, 'HS256');
                Response::json([
                    'token_type' => 'Bearer',
                    'access_token' => $jwt,
                    'expires_in' => $this->config['jwt']['expiration_time']
                ]);
            } else {
                Response::json(['error' => true, 'message' => 'Invalid username or password.'], 401);
            }
        }
    }

    // --- How AppGiniHelper::validateUser might look (simplified) ---
    /*
    // In core/AppGiniHelper.php
    class AppGiniHelper {
        public static function validateUser($db, $username, $password) {
            // IMPORTANT: Use prepared statements here to prevent SQL Injection
            // This is a conceptual example. AppGini's makeSafe() is for SQL parts, not values in PDO.
            $safeUsername = strtolower($username); // AppGini usernames are typically case-insensitive

            $stmt = $db->prepare("SELECT memberID, groupID, passMD5 FROM membership_users WHERE LCASE(memberID) = ?");
            $stmt->execute([$safeUsername]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && md5($password) === $user['passMD5']) {
                return [
                    'memberID' => $user['memberID'],
                    'username' => $user['memberID'], // AppGini uses memberID as username
                    'groupID' => $user['groupID']
                ];
            }
            return null;
        }
    }
    */
    ?>
    ```

**2.3. Token Validation (Middleware Approach)**

*   **Location:**
    JWT validation should occur early in the request lifecycle for any protected endpoint. This is typically handled in a central routing script (e.g., `api/v1/index.php`) or, more elegantly, as a dedicated authentication middleware that processes the request before it reaches the specific CRUD controllers. The login endpoint (`/api/v1/login`) and any public documentation endpoints should be excluded from this validation.

*   **Process:**
    1.  Extract the JWT from the `Authorization` HTTP header. The common convention is `Authorization: Bearer <token>`.
    2.  If the route is protected and no token is present, or the header is malformed, return a `401 Unauthorized` error immediately.
    3.  Attempt to decode the JWT using the same secret key and algorithm (`HS256`) used for signing. The `firebase/php-jwt` library's `decode` method is used for this.
    4.  The library will automatically handle checks for `nbf` (not before), `iat` (issued at), and `exp` (expiration) claims if present in the token. It will throw specific exceptions for failures:
        *   `ExpiredException`: If the token is past its `exp` time.
        *   `SignatureInvalidException`: If the token signature does not match (tampered or wrong key).
        *   Other exceptions for malformed tokens, etc.
        These exceptions should be caught, and appropriate `401 Unauthorized` JSON responses should be returned.
    5.  If the token is successfully decoded and validated, the user information from the payload (e.g., `user_id`, `username`, `group_id`) should be made available to subsequent parts of the application (e.g., controllers, permission service). This can be done by storing it in a global request context object, a static property of a context class, or by passing it as a parameter to controllers.

*   **Example (Conceptual PHP for `api/v1/index.php` or an AuthMiddleware):**

    ```php
    <?php
    // File: api/v1/index.php (Simplified - acting as a front controller/router with middleware logic)
    // Or this logic could be in a dedicated AuthMiddleware class called by the router.

    // define('APP_ENTRY_POINT', true); // Define a constant to check in included files

    // require_once __DIR__ . '/../core/Response.php';
    // require_once __DIR__ . '/../core/AppContext.php'; // For storing current user context
    // require_once __DIR__ . '/../vendor/autoload.php'; // For firebase/php-jwt

    // use Firebase\JWT\JWT;
    // use Firebase\JWT\Key;
    // use Firebase\JWT\ExpiredException;
    // use Firebase\JWT\SignatureInvalidException;
    // use Firebase\JWT\BeforeValidException; // For 'nbf' (not before) claim

    // $config = require __DIR__ . '/../config/config.php'; // Load JWT_SECRET, etc.

    // // Basic routing
    // $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // $requestMethod = $_SERVER['REQUEST_METHOD'];

    // // Define public routes that don't require authentication
    // $publicRoutes = [
    //     '/api/v1/login' => ['POST'] // Method specific public routes
    //     // Add other public routes like '/api/v1/docs' => ['GET']
    // ];

    // $isPublicRoute = false;
    // if (isset($publicRoutes[$requestUri]) && in_array($requestMethod, $publicRoutes[$requestUri])) {
    //     $isPublicRoute = true;
    // }

    // if (!$isPublicRoute) {
    //     $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    //     if (!$authHeader || !preg_match('/^Bearer\s(\S+)$/i', $authHeader, $matches)) {
    //         Response::json(['error' => true, 'message' => 'Authorization header missing or malformed.'], 401);
    //         exit;
    //     }
    //     $token = $matches[1];
    //     $jwtSecretKey = $config['jwt']['secret_key'];

    //     if (empty($jwtSecretKey)) {
    //         error_log("JWT secret key is not configured for token validation.");
    //         Response::json(['error' => true, 'message' => 'Authentication server configuration error.'], 500);
    //         exit;
    //     }

    //     try {
    //         // The Key object is used from firebase/php-jwt v6.0+
    //         $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));

    //         // Store user info from token payload for later use in the application context
    //         // This makes $decoded->user_id, $decoded->group_id, etc. available
    //         AppContext::set('current_user', (array)$decoded);

    //     } catch (ExpiredException $e) {
    //         Response::json(['error' => true, 'message' => 'Token has expired.'], 401);
    //         exit;
    //     } catch (SignatureInvalidException $e) {
    //         Response::json(['error' => true, 'message' => 'Token signature invalid.'], 401);
    //         exit;
    //     } catch (BeforeValidException $e) {
    //         Response::json(['error' => true, 'message' => 'Token not yet valid.'], 401);
    //         exit;
    //     } catch (Exception $e) { // Catch other JWT-related exceptions or general exceptions
    //         Response::json(['error' => true, 'message' => 'Invalid token: ' . $e->getMessage()], 401);
    //         exit;
    //     }
    // }

    // // --- Proceed to routing and controller dispatching ---
    // // Example:
    // // if ($requestUri === '/api/v1/login' && $requestMethod === 'POST') {
    // //     $authController = new AuthController($config, $db); // $db needs to be initialized
    // //     $authController->login();
    // // } elseif (preg_match('/^\/api\/v1\/(\w+)$/', $requestUri, $routeMatches) && $requestMethod === 'GET') {
    // //     $tableName = $routeMatches[1];
    // //     // Ensure AppContext::get('current_user') is available if not a public route
    // //     // $getController = new GetController($config, $db, AppContext::get('current_user'));
    // //     // $getController->handleRequest($tableName);
    // // } else {
    // //     Response::json(['error' => true, 'message' => 'Endpoint not found.'], 404);
    // // }
    ?>
    ```

**2.4. Authorization: Integrating AppGini Permissions**

Once a user is authenticated via JWT, the API must authorize their requests based on AppGini's permission system.

*   **Leveraging AppGini's Structure:**
    AppGini's permission model is centered around groups:
    *   `membership_users`: Contains user details, including their `memberID` (username) and `groupID`.
    *   `membership_groups`: Defines group names.
    *   `membership_grouppermissions`: Links `groupID` to specific tables and permissions (allowInsert, allowView, allowEdit, allowDelete). A value of `1` typically means allow, `0` (or NULL) means deny.

*   **`AppGiniHelper::getMemberInfo()` or Direct Queries:**
    The standard AppGini function `getMemberInfo($memberID = '')` (often found in `admin/incFunctions.php` or `lib.php`) is the primary way the AppGini UI fetches all details about a user, including their specific permissions for each table.
    *   **Ideal Scenario:** If `incFunctions.php` (or its equivalent containing `getMemberInfo`) can be safely included and used within the API context (without conflicts like session handling or admin-only checks), this is the preferred method. The `memberID` extracted from the JWT payload (`$decoded->user_id`) would be passed to this function.
    *   **Alternative (Replication of Logic):** If `getMemberInfo()` is not directly usable (e.g., it relies on admin area sessions, outputs HTML, or has too many unrelated dependencies), its core permission-fetching logic must be replicated within the API, likely in `AppGiniHelper.php` or a dedicated `PermissionService.php`. This involves:
        1.  Getting the `groupID` from the JWT payload (`$decoded->group_id`).
        2.  Querying `membership_grouppermissions` for that `groupID` to get all table-specific permissions.
        3.  Querying `membership_users` for any user-specific details if necessary (though group permissions are primary for CRUD).

*   **Permission Checks in Controllers/PermissionService:**
    Before executing any CRUD operation (GET, POST, PUT, PATCH, DELETE) on a table, the system must verify if the authenticated user's group has the required permission.
    *   The JWT payload provides `user_id` and `group_id`. The `PermissionService` would use the `group_id` (or fetch full `memberInfo` using `user_id`) to check against the requested `tableName` and `action`.
    *   AppGini permission fields in `membership_grouppermissions` are typically named `allowInsert`, `allowView`, `allowEdit`, `allowDelete`. The `PermissionService` would map API actions (e.g., 'create', 'read', 'update', 'delete') to these fields.
    *   If permission is denied, a `403 Forbidden` JSON response should be returned.

*   **Example (Conceptual PHP within a controller action or `PermissionService`):**

    ```php
    <?php
    // File: core/PermissionService.php (Conceptual)
    // class PermissionService {
    //     private $db;
    //     private $currentUserContext; // Contains decoded JWT payload (user_id, group_id, etc.)

    //     public function __construct($db, $currentUserContext) {
    //         $this->db = $db;
    //         $this->currentUserContext = $currentUserContext;
    //     }

    //     public function canPerformAction($tableName, $action) {
    //         if (!$this->currentUserContext || !isset($this->currentUserContext['group_id'])) {
    //             return false; // No user context or group_id, deny by default
    //         }
    //         $groupID = $this->currentUserContext['group_id'];

    //         // Map API actions to AppGini's permission fields
    //         $permissionFieldMap = [
    //             'view'   => 'allowView',
    //             'create' => 'allowInsert',
    //             'edit'   => 'allowEdit',
    //             'delete' => 'allowDelete'
    //         ];

    //         if (!isset($permissionFieldMap[$action])) {
    //             return false; // Unknown action
    //         }
    //         $permissionField = $permissionFieldMap[$action];

    //         // Fetch permissions for the group and table
    //         // IMPORTANT: Use prepared statements
    //         $stmt = $this->db->prepare(
    //             "SELECT {$permissionField} FROM membership_grouppermissions
    //              WHERE groupID = ? AND tableName = ?"
    //         );
    //         $stmt->execute([$groupID, $tableName]);
    //         $permission = $stmt->fetchColumn();

    //         return $permission == 1; // This is simplified; AppGini uses 0, 1, 2, 3
                                      // 0 = no, 1 = group, 2 = owner, 3 = all (for view)
                                      // For edit/delete: 0 = no, 1 = group, 2 = owner
                                      // This function should return the actual permission level (0,1,2,3)
                                      // to be interpreted by the controller.
    //     }

    //     // Method to check owner-based permissions
    //     public function isOwner($tableName, $recordId) {
    //         if (!$this->currentUserContext || !isset($this->currentUserContext['user_id'])) {
    //             return false;
    //         }
    //         $currentUserID = $this->currentUserContext['user_id']; // This is memberID (username)

    //         // Determine the ownership field for the table.
    //         // In AppGini, this is often a VARCHAR(40) field named 'memberID' storing the username.
    //         // Or it could be a custom field if so configured.
    //         $ownershipField = AppGiniHelper::getOwnershipField($tableName); // Needs implementation
    //         if (!$ownershipField) return true; // If no ownership field, not owner-restricted

    //         $pkField = AppGiniHelper::getPrimaryKeyField($tableName); // Needs implementation
    //         if (!$pkField) return false; // Cannot check ownership without PK

    //         $stmt = $this->db->prepare(
    //             "SELECT COUNT(1) FROM `{$tableName}` WHERE `{$pkField}` = ? AND `{$ownershipField}` = ?"
    //         );
    //         $stmt->execute([$recordId, $currentUserID]); // $currentUserID is the memberID/username

    //         return $stmt->fetchColumn() > 0;
    //     }
    // }

    // --- Usage in a Controller (e.g., PutController.php) ---
    // // $permissionService = new PermissionService($this->db, AppContext::get('current_user'));
    // // $tableName = 'orders';
    // // $recordId = $this->request->getId();

    // // $permissionLevel = $permissionService->getPermissionLevel($tableName, 'edit'); // Hypothetical
    // // if ($permissionLevel == 0) { // 0 = No permission
    // //     Response::json(['error' => true, 'message' => 'Forbidden: You do not have permission.'], 403);
    // //     exit;
    // // }
    // // if ($permissionLevel == 2 && !$permissionService->isOwner($tableName, $recordId)) { // 2 = Owner-only permission
    // //    Response::json(['error' => true, 'message' => 'Forbidden: You can only edit your own records.'], 403);
    // //    exit;
    // // }
    // // Proceed with operation if permissionLevel is 1 (group) or (2 and isOwner is true)
    ?>
    ```

*   **Owner-Based Permissions:**
    AppGini allows configuring permissions where users can only view, edit, or delete their own records. This is typically stored in `membership_grouppermissions` with values like `2` (owner) for `allowEdit` or `allowDelete` (or `allowView`).
    *   The `PermissionService` or controller logic must handle this. AppGini uses distinct numeric values for permission types:
        *   For `allowView`: `0` (No), `1` (Group), `2` (Owner), `3` (All).
        *   For `allowEdit`/`allowDelete`: `0` (No), `1` (Group), `2` (Owner).
    *   The `canPerformAction` (or a more specific `getPermissionLevel`) method in `PermissionService` should fetch this numeric value.
    *   The controller then interprets this value:
        1.  If `0`, deny access.
        2.  If `1` (Group), allow access based on group membership.
        3.  If `2` (Owner), an additional check is required:
            *   Identify the "owner" field in the target table. AppGini often uses a field named `memberID` (a VARCHAR(40) storing the username) in tables to denote ownership. This field name might be discoverable via AppGini's field configuration files or a helper function.
            *   Compare the value of this owner field in the specific record being accessed/modified with the `user_id` (memberID/username) of the authenticated user from the JWT.
            *   If they do not match, the operation must be denied with a `403 Forbidden` error.
        4.  If `3` (All, for view only), allow access.

**2.5. Refresh Tokens (Optional but Recommended for Better UX)**

*   **Concept:** Short-lived access tokens (JWTs) enhance security by limiting the time window an attacker has if a token is compromised. However, they can degrade user experience by requiring frequent re-logins. Refresh tokens address this.
    *   When a user logs in, they receive both a short-lived JWT (access token) and a longer-lived refresh token.
    *   The refresh token is stored securely by the client (e.g., in an HTTP-only cookie or secure storage).
    *   When the access token expires, the client sends the refresh token to a dedicated endpoint (e.g., `/api/v1/refresh_token`).
    *   The server validates the refresh token (checking it against a database of active refresh tokens, its expiry, and ensuring it hasn't been revoked).
    *   If valid, the server issues a new short-lived access token (and optionally a new refresh token, rotating them for better security).
*   **Implementation Complexity:**
    This adds significant complexity:
    *   A new endpoint (`/api/v1/refresh_token`).
    *   Secure storage for refresh tokens on the server-side (e.g., a database table mapping refresh tokens to users, with expiry dates and a revocation status).
    *   Logic for issuing, validating, and revoking refresh tokens.
*   **Recommendation:**
    While beneficial for UX in many applications, implementing refresh tokens can be deferred for an initial API version if simplicity is prioritized. The focus can first be on robust access token authentication and authorization. The `expires_in` value returned with the JWT helps clients manage token expiration.

This detailed breakdown for Section 2 should provide a clear plan for implementing robust authentication and authorization mechanisms for the AppGini API.
