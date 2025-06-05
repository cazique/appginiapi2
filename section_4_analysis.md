**Section 4: Error Handling and HTTP Codes**

This section defines a standardized approach to error handling within the API, including a consistent JSON error response format and the correct use of HTTP status codes. Providing clear, structured error messages and appropriate status codes greatly improves the API's usability and helps clients diagnose and resolve issues effectively.

**4.1. Standard JSON Error Response Format**

A consistent structure for all error responses is essential for client-side error handling.

*   **Proposed Format:**
    All error responses from the API should conform to the following JSON structure:

    ```json
    {
      "error": true,
      "message": "A human-readable error message describing the issue.",
      "code": "MACHINE_READABLE_ERROR_CODE", // Optional: A specific code for client-side logic
      "details": {} // Optional: An object or array containing more specific error details
    }
    ```

    *   `"error": true`: A boolean flag clearly indicating that the response represents an error.
    *   `"message"`: A human-readable string describing the general nature of the error. This message should be clear and concise. It should provide enough information for a developer to understand the issue but avoid exposing sensitive system details (like full file paths or raw SQL queries) in a production environment.
    *   `"code"` (Optional): A machine-readable string (e.g., `VALIDATION_ERROR`, `UNAUTHENTICATED`, `INSUFFICIENT_PERMISSIONS`). This allows client applications to implement specific logic based on the error type, beyond just relying on the HTTP status code.
    *   `"details"` (Optional): An object or array providing more specific information about the error. This is particularly useful for:
        *   **Validation errors:** Can list issues for multiple fields.
        *   **Complex errors:** Can break down the error into more granular parts.

*   **Example for a Validation Error:**
    If a user submits data that fails validation (e.g., missing required fields, invalid formats):

    ```json
    {
      "error": true,
      "message": "Validation failed. Please check the provided data.",
      "code": "VALIDATION_ERROR",
      "details": {
        "field_name1": ["Error message for field 1, e.g., 'This field is required.'", "Another error for field 1."],
        "field_name2": ["Error message for field 2, e.g., 'Must be a valid email address.'"]
      }
    }
    ```
    In this example, `details` is an object where each key is a field name, and the value is an array of error messages pertaining to that field.

**4.2. HTTP Status Codes**

The API must use standard HTTP status codes to indicate the overall outcome of a request. This is a fundamental aspect of RESTful API design.

*   **Commonly Used Codes and Their Significance:**

    *   **Success Codes:**
        *   `200 OK`: The request was successful. The response body typically contains the requested data (for GET) or a representation of the successfully modified resource (for PUT/PATCH).
        *   `201 Created`: The request was successful, and a new resource was created as a result (e.g., after a POST request). The response *should* include a `Location` header containing the URI of the newly created resource and *may* include a representation of the new resource in the body.
        *   `204 No Content`: The request was successful, but there is no representation to return in the response body. This is often used for successful `DELETE` operations or `PUT`/`PATCH` operations where the server doesn't return the updated resource.

    *   **Client Error Codes:**
        *   `400 Bad Request`: The server cannot or will not process the request due to an apparent client error. This can be due to malformed request syntax, invalid request message framing, or deceptive request routing. It can also be used for general validation errors if a more specific code like `422` is not preferred.
        *   `401 Unauthorized`: Authentication is required to access the resource, and the request has failed authentication or authentication has not yet been provided. The client should attempt to authenticate (or re-authenticate) and try the request again.
        *   `403 Forbidden`: The server understood the request, but it is refusing to fulfill it. Unlike `401`, authentication will not help, and the request should not be repeated. This indicates that the authenticated user does not have the necessary permissions to perform the requested action on the resource.
        *   `404 Not Found`: The server has not found any resource matching the Request-URI. This typically means the specific endpoint or the requested resource (e.g., `/api/v1/orders/99999` where order 99999 doesn't exist) could not be found.
        *   `405 Method Not Allowed`: The HTTP method used in the request (e.g., `POST`, `GET`, `DELETE`) is not allowed for the resource identified by the Request-URI. The response *must* include an `Allow` header listing the valid methods for the resource (e.g., `Allow: GET, POST`).
        *   `409 Conflict`: The request could not be completed because of a conflict with the current state of the target resource. This is often used when trying to create a resource that would violate a uniqueness constraint (e.g., creating a user with an email that already exists).
        *   `422 Unprocessable Entity`: (WebDAV; RFC 4918) The server understands the content type of the request entity, and the syntax of the request entity is correct, but it was unable to process the contained instructions. This code is particularly well-suited for semantic errors, such as validation failures where the data format is syntactically correct but the values are invalid (e.g., an email field is provided but the value isn't a valid email address). It allows for returning detailed error messages (like the `details` object) in the response body.

    *   **Server Error Codes:**
        *   `500 Internal Server Error`: A generic error message indicating that the server encountered an unexpected condition that prevented it from fulfilling the request. This usually means something went wrong on the server side (e.g., unhandled exception, database error, misconfiguration). **In production environments, detailed error information like stack traces or raw exception messages should never be sent to the client with a 500 error.** Such details should be logged on the server.

**4.3. Implementation in `Response` Class / Error Handling Logic**

A centralized `Response` class (as proposed in Section 1.3) is key to ensuring consistent error formatting and status code usage.

*   **Conceptual `Response.php` (or part of a base controller):**

    ```php
    <?php
    // File: api/core/Response.php (Conceptual)

    class Response {
        /**
         * Sends a JSON response.
         *
         * @param mixed $data Data to be JSON encoded. For errors, this can be an array
         *                    conforming to the error structure or just an error message string.
         * @param int $statusCode HTTP status code.
         * @param array $headers Additional headers to send.
         */
        public static function json($data, int $statusCode = 200, array $headers = []) {
            if (!headers_sent()) { // Check if headers already sent to avoid errors
                header_remove(); // Remove any existing headers set by PHP or previous code
                http_response_code($statusCode);
                header('Content-Type: application/json; charset=utf-8');
                // Add other standard headers like CORS (to be discussed in a later section)
                // header('Access-Control-Allow-Origin: *'); // Example CORS header
                foreach ($headers as $headerName => $headerValue) {
                    header("{$headerName}: {$headerValue}");
                }
            }

            $responseBody = $data;
            if ($statusCode >= 400) { // It's an error, ensure standard error format
                $errorResponse = ['error' => true];
                if (is_string($data)) { // Simple error message string passed
                    $errorResponse['message'] = $data;
                } elseif (is_array($data)) { // Array possibly containing message, code, details
                    $errorResponse['message'] = $data['message'] ?? 'An error occurred.';
                    if (isset($data['code'])) $errorResponse['code'] = $data['code'];
                    if (isset($data['details'])) $errorResponse['details'] = $data['details'];
                } else { // Fallback for unknown error data type
                    $errorResponse['message'] = 'An unexpected error format was encountered.';
                }
                $responseBody = $errorResponse;
            }

            echo json_encode($responseBody);
            exit; // Terminate script execution after sending response
        }

        /**
         * Convenience method for sending generic error responses.
         */
        public static function error(string $message, int $statusCode = 400, $details = null, ?string $errorCode = null) {
            $responseData = ['message' => $message];
            if ($details !== null) {
                $responseData['details'] = $details;
            }
            if ($errorCode !== null) {
                $responseData['code'] = $errorCode;
            }
            self::json($responseData, $statusCode);
        }

        // Specific error type convenience methods:

        public static function validationError(array $errors, string $message = 'Validation failed. Please check the provided data.') {
            self::error($message, 422, $errors, 'VALIDATION_ERROR'); // Using 422 Unprocessable Entity
        }

        public static function badRequest(string $message = 'Bad request.', $details = null, ?string $errorCode = 'BAD_REQUEST') {
            self::error($message, 400, $details, $errorCode);
        }

        public static function notFound(string $message = 'The requested resource was not found.') {
            self::error($message, 404, null, 'NOT_FOUND');
        }

        public static function unauthorized(string $message = 'Authentication is required and has failed or has not been provided.') {
            // Optionally add WWW-Authenticate header for some 401 scenarios, e.g.,
            // $headers = ['WWW-Authenticate' => 'Bearer realm="api"'];
            // self::json(['message' => $message, 'code' => 'UNAUTHENTICATED'], 401, $headers);
            self::error($message, 401, null, 'UNAUTHENTICATED');
        }

        public static function forbidden(string $message = 'You do not have permission to access this resource.') {
            self::error($message, 403, null, 'FORBIDDEN');
        }

        public static function methodNotAllowed(array $allowedMethods, string $message = 'Method not allowed.') {
            $responseData = ['message' => $message, 'code' => 'METHOD_NOT_ALLOWED'];
            self::json($responseData, 405, ['Allow' => implode(', ', $allowedMethods)]);
        }

        public static function conflict(string $message = 'A conflict occurred with the current state of the resource.') {
            self::error($message, 409, null, 'CONFLICT');
        }

        public static function internalServerError(string $publicMessage = 'An unexpected internal error occurred. Please try again later.', ?string $logMessage = null) {
            // In production, log the detailed error but send a generic message to the client.
            if ($logMessage) {
                error_log("Internal Server Error: " . $logMessage); // Use a proper logger in a real app
            }
            self::error($publicMessage, 500, null, 'INTERNAL_SERVER_ERROR');
        }
    }
    ?>
    ```

**4.4. Code Examples of Error Scenarios:**

*   **Validation Failed (e.g., missing required field in POST/PUT):**
    *   Controller logic (e.g., in `PostController.php` or `PutController.php`):
        ```php
        <?php
        // // In a controller (e.g., PostController.php)
        // $input = json_decode(file_get_contents('php://input'), true);
        // $errors = [];

        // // Assume a Validator class or logic exists
        // // $validator = new Validator($input, ['name' => 'required|string', 'email' => 'required|email']);
        // // if ($validator->fails()) {
        // //     Response::validationError($validator->getErrors());
        // // }

        // // Manual validation example:
        // if (empty($input['name'])) {
        //     $errors['name'][] = 'The name field is required.';
        // }
        // if (empty($input['email'])) {
        //     $errors['email'][] = 'The email field is required.';
        // } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        //     $errors['email'][] = 'The email must be a valid email address.';
        // }

        // if (!empty($errors)) {
        //     Response::validationError($errors); // Uses HTTP 422
        //     // Alternatively, for a general bad request:
        //     // Response::badRequest("Validation failed. Please check your input.", $errors); // Uses HTTP 400
        // }
        // // Proceed with resource creation...
        ?>
        ```
    *   Expected Response (HTTP 422):
        ```json
        {
          "error": true,
          "message": "Validation failed. Please check the provided data.",
          "code": "VALIDATION_ERROR",
          "details": {
            "name": ["The name field is required."],
            "email": ["The email must be a valid email address."]
          }
        }
        ```

*   **Access Not Authorized (e.g., user tries to edit a resource without permission):**
    *   Permission check logic (e.g., in a base controller or called from a specific controller action, using `PermissionService` from Section 2):
        ```php
        <?php
        // // In AbstractCrudController.php or specific controller
        // // Assume $this->permissionService and $this->currentUser are available
        // $tableName = 'products';
        // $action = 'edit'; // or 'delete', 'create', 'view'

        // if (!$this->permissionService->canPerformAction($this->currentUser['group_id'], $tableName, $action)) {
        //     Response::forbidden("You do not have permission to {$action} {$tableName}.");
        // }
        // // Further check for owner-only permissions if applicable
        ?>
        ```
    *   Expected Response (HTTP 403):
        ```json
        {
          "error": true,
          "message": "You do not have permission to edit products.",
          "code": "FORBIDDEN"
        }
        ```

*   **Resource Not Found (e.g., GET `/api/v1/your_table/99999` where ID 99999 does not exist):**
    *   Controller logic (e.g., in `GetController.php`):
        ```php
        <?php
        // // In GetController.php, when fetching a single record by ID
        // $recordId = $this->request->getSegment(3); // Assuming /api/v1/table_name/{id}
        // $record = AppGiniHelper::fetchRecord($this->db, $tableName, $recordId);

        // if (!$record) {
        //     Response::notFound("The resource with ID {$recordId} was not found in {$tableName}.");
        // }
        // // Return $record...
        ?>
        ```
    *   Expected Response (HTTP 404):
        ```json
        {
          "error": true,
          "message": "The resource with ID 99999 was not found in products.",
          "code": "NOT_FOUND"
        }
        ```

*   **Internal Server Error (e.g., database connection fails, unhandled exception):**
    *   This is often best handled by a global exception handler set at the entry point of the API (e.g., `api/v1/index.php`).
        ```php
        <?php
        // // In api/v1/index.php (or a bootstrap file)

        // // Register a global exception handler
        // set_exception_handler(function(Throwable $exception) {
        //     // In a real application, use a PSR-3 compliant logger (e.g., Monolog)
        //     $logMessage = "Uncaught Exception: " . $exception->getMessage() .
        //                   " in " . $exception->getFile() . ":" . $exception->getLine() .
        //                   "\nStack trace:\n" . $exception->getTraceAsString();

        //     // Log the detailed message to server logs
        //     error_log($logMessage);

        //     // Send a generic error message to the client
        //     // Avoid sending $exception->getMessage() directly in production if it might contain sensitive info
        //     if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        //         Response::internalServerError('An unexpected error occurred: ' . $exception->getMessage(), $logMessage);
        //     } else {
        //         Response::internalServerError('An unexpected internal error occurred. Please try again later or contact support.', $logMessage);
        //     }
        // });

        // // Example of a try-catch within specific risky code (e.g., database operations)
        // try {
        //     // $result = $this->db->query("SOME RISKY SQL QUERY THAT MIGHT FAIL");
        //     // if ($result === false) {
        //     //    throw new PDOException("Database query failed.");
        //     // }
        // } catch (PDOException $e) {
        //     $logMessage = "Database Error: " . $e->getMessage() . " (SQLSTATE: " . $e->getCode() . ")";
        //     // In dev, you might want to show more detail, but in prod, keep it generic.
        //     Response::internalServerError('A database error occurred while processing your request.', $logMessage);
        // }
        ?>
        ```
    *   Expected Response (HTTP 500):
        ```json
        {
          "error": true,
          "message": "An unexpected internal error occurred. Please try again later or contact support.",
          "code": "INTERNAL_SERVER_ERROR"
        }
        ```

By implementing this consistent error handling strategy, the API becomes more robust, predictable, and easier for client developers to integrate with and debug.
