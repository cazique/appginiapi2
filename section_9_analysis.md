**Section 9: Testing and QA**

A robust testing and Quality Assurance (QA) strategy is paramount for developing a reliable and maintainable API. This section outlines different testing layers, suggests tools like PHPUnit and Postman/Newman, and emphasizes the need for a staging environment for final validation.

**9.1. Importance of API Testing**

Thorough API testing is crucial for several reasons:
*   **Ensure Reliability and Correctness:** Verifies that the API consistently returns the correct data, in the correct format, and with the correct HTTP status codes for various inputs and scenarios.
*   **Prevent Regressions:** As the API evolves with new features or code refactoring, automated tests act as a safety net, quickly identifying if existing functionalities have been unintentionally broken.
*   **Verify Security Mechanisms:** Confirms that authentication (e.g., JWT validation) and authorization (e.g., AppGini role-based permissions) are working as expected, preventing unauthorized access or data manipulation.
*   **Validate Business Logic and Data Integrity:** Ensures that the API correctly implements business rules and that data operations (create, update, delete) maintain the integrity of the underlying database, respecting AppGini's field validations and constraints.
*   **Provide Confidence for Deployment:** A comprehensive suite of passing tests gives developers and stakeholders confidence that the API is stable and ready for deployment to production environments.
*   **Improve Design and Maintainability:** Writing tests often encourages better API design, leading to more modular, testable, and maintainable code.
*   **Documentation through Tests:** Test cases can serve as a form of executable documentation, illustrating how the API is intended to be used and what responses to expect.

**9.2. Testing Layers**

A comprehensive testing strategy typically involves multiple layers:

*   **Unit Tests:**
    *   Focus on testing the smallest individual components (e.g., classes, methods, functions) of the application in isolation from the rest of the system.
    *   Dependencies are often "mocked" or "stubbed" to ensure that the test focuses solely on the unit's logic.
    *   **Example:** Testing a `Validator` class to ensure its validation rules work correctly, testing a specific function within `AppGiniHelper.php` for data transformation, or testing a method in `AuthService.php` that generates a JWT payload.

*   **Integration Tests:**
    *   Verify the interaction and communication between two or more components or layers of the application.
    *   These tests ensure that different parts of the system work together as expected.
    *   **Example:** Testing if a controller method correctly calls the `PermissionService` and then interacts with a database model/repository class to fetch or save data. This might involve a real (test) database connection.

*   **End-to-End (E2E) / API Tests:**
    *   Test the entire application flow from the perspective of an external client. For an API, this means making actual HTTP requests to the API endpoints and verifying the HTTP responses (status codes, headers, body content).
    *   These tests validate the complete request-response cycle, including routing, authentication, controller logic, service interaction, database operations, and response formatting.
    *   This is the primary focus for tools like Postman and Newman.

For the AppGini REST API, a pragmatic approach would be to focus PHPUnit on unit and some integration tests for core services and business logic, while leveraging Postman/Newman for comprehensive end-to-end API testing.

**9.3. PHPUnit for Unit and Integration Tests**

PHPUnit is the de facto standard testing framework for PHP. It's well-suited for writing unit and integration tests for the API's core PHP classes and services.

*   **Setup:**
    1.  **Installation:** Add PHPUnit as a development dependency using Composer:
        ```bash
        composer require --dev phpunit/phpunit
        ```
    2.  **Configuration:** Create a `phpunit.xml.dist` (or `phpunit.xml`) configuration file in the project root. This file defines test suite locations, bootstrap files, code coverage options, etc.
        ```xml
        <!-- phpunit.xml.dist -->
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
                 bootstrap="vendor/autoload.php" <!-- Or a custom bootstrap file -->
                 colors="true">
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests/unit</directory>
                </testsuite>
                <testsuite name="Integration">
                    <directory>tests/integration</directory>
                </testsuite>
            </testsuites>
            <coverage processUncoveredFiles="true">
                <include>
                    <directory suffix=".php">api/core</directory> <!-- Adjust to your source directory -->
                    <directory suffix=".php">api/v1/Controllers</directory>
                </include>
            </coverage>
        </phpunit>
        ```
    3.  **Test Directory Structure:** Organize tests in a dedicated `tests/` directory, typically with subdirectories for `unit/` and `integration/` tests.

*   **What to Test with PHPUnit:**
    *   **Core Logic (Unit Tests):**
        *   `AuthService.php`: Test JWT payload creation, token signing logic (if custom), and token validation logic (mocking the actual JWT library calls if necessary to test surrounding logic).
        *   `PermissionService.php`: Test permission checking logic against various mock user roles and table/action combinations.
        *   `Validator.php` (or similar validation classes): Test individual validation rules and the overall validation process for request data.
        *   Helper functions or classes (e.g., in `AppGiniHelper.php`): Test data transformation, sanitization, or AppGini-specific utility functions.
    *   **Database Interactions (Integration Tests):**
        *   If you have model classes or repositories that encapsulate database queries, write tests for these methods to ensure they construct correct SQL (if not using an ORM that abstracts this) and return expected data.
        *   These tests typically require a dedicated test database that can be reset or seeded before each test run or suite.
    *   **Controller Logic (Integration/Unit - depending on approach):**
        *   Testing controllers with PHPUnit can be complex without a framework that provides good mocking capabilities for HTTP requests and responses.
        *   **Pragmatic Approach for this API:** Given the API's proposed structure, it might be more effective to focus PHPUnit on the core services mentioned above and use Postman/Newman for thorough testing of the HTTP layer (controllers, routing, request/response lifecycle).
        *   If attempting controller tests with PHPUnit, you would need to mock `$_GET`, `$_POST`, `$_SERVER`, `file_get_contents('php://input')`, and any global AppGini functions or external dependencies (like the `Response` class methods if they `exit`).

*   **Example (Conceptual PHPUnit Test for `PermissionService`):**
    ```php
    <?php
    // File: tests/unit/PermissionServiceTest.php
    // Assuming App\Core\PermissionService is your class and it's autoloadable via PSR-4.

    use PHPUnit\Framework\TestCase;
    use App\Core\PermissionService; // Adjust namespace as per your structure
    // Assume AppGiniHelper provides a way to get mock or simplified permission sets
    // use App\Core\AppGiniHelper;

    class PermissionServiceTest extends TestCase
    {
        private $permissionService;
        // Mock database or AppGini environment if PermissionService depends on them directly.
        // For simplicity, this example assumes PermissionService can take permissions directly or has a mockable dependency.

        protected function setUp(): void
        {
            // Example: If PermissionService needs a DB connection for real queries
            // $mockDb = $this->createMock(PDO::class);
            // $this->permissionService = new PermissionService($mockDb);

            // Simpler: Assume PermissionService works with a permissions array
            $this->permissionService = new PermissionService();
        }

        public function testUserWithSpecificPermissionCanPerformAction()
        {
            // Mock structure similar to what getMemberInfo() might return for permissions part
            $userPermissions = [
                'products_view' => 1, // Has view permission for products
                'products_edit' => 1, // Has edit permission for products
                'orders_view'   => 0  // Does NOT have view permission for orders
            ];

            // Adapt this to how your PermissionService actually consumes permissions
            // For instance, if it takes groupID and queries DB, you'd mock the DB response.
            // This example assumes a simplified check based on a pre-loaded array.

            $this->assertTrue(
                $this->permissionService->checkGroupTablePermission($userPermissions, 'products', 'view'),
                "User should have 'view' permission for 'products'."
            );
            $this->assertTrue(
                $this->permissionService->checkGroupTablePermission($userPermissions, 'products', 'edit'),
                "User should have 'edit' permission for 'products'."
            );
        }

        public function testUserWithoutPermissionCannotPerformAction()
        {
            $userPermissions = [
                'products_view' => 1,
                'orders_view'   => 0
            ];

            $this->assertFalse(
                $this->permissionService->checkGroupTablePermission($userPermissions, 'orders', 'view'),
                "User should NOT have 'view' permission for 'orders'."
            );
        }

        public function testAdminGroupHasAllPermissions()
        {
            // Example: If your PermissionService has special logic for admin group (e.g., groupID '2')
            // This requires more setup if it involves DB lookups for group name 'Admins'
            // $adminPermissions = AppGiniHelper::getPermissionsForGroup('Admins'); // Hypothetical
            // $this->assertTrue($this->permissionService->checkGroupTablePermission($adminPermissions, 'any_table', 'any_action'));
            $this->markTestIncomplete('Admin permission test needs more specific implementation details for PermissionService.');
        }

        // Add tests for owner-based permission logic if PermissionService handles that.
        // e.g., testCanEditOwnRecord(), testCannotEditOthersRecordIfOwnerOnly()
    }
    ?>
    ```
    *Note: The conceptual test for `PermissionService` would need to be adapted based on the actual implementation of how permissions are fetched and checked (e.g., direct DB query vs. working with `getMemberInfo()` output).*

*   **Running Tests:**
    Execute tests from the project root directory:
    ```bash
    vendor/bin/phpunit
    ```

**9.4. Postman/Newman for End-to-End API Testing**

Postman (GUI) and Newman (CLI) are excellent tools for comprehensive end-to-end testing of the API's HTTP endpoints.

*   **Postman:**
    1.  **Create a Collection:** Organize all API requests for the AppGini API within a Postman Collection.
    2.  **Add Requests:** For each endpoint and HTTP method (`/login`, `GET /tu_tabla`, `POST /tu_tabla`, `GET /tu_tabla/{id}`, `PUT /tu_tabla/{id}`, `DELETE /tu_tabla/{id}`), create a corresponding request in the collection.
    3.  **Use Environment Variables:** Define Postman environments (e.g., "Local", "Staging", "Production") to manage variables like `{{baseUrl}}`, `{{adminUsername}}`, `{{adminPassword}}`. This makes it easy to run the same tests against different environments. Store dynamic values like `{{jwtToken}}` and `{{createdItemId}}` as environment or collection variables, updated by scripts in preceding requests.
    4.  **Write JavaScript Tests:** In the "Tests" tab of each Postman request, write JavaScript code using the `pm` API to assert various conditions:
        *   **Status Codes:** `pm.response.to.have.status(200);`
        *   **Response Body Structure:** `pm.expect(jsonData.data).to.be.an('array');`, `pm.expect(jsonData.pagination).to.be.an('object');`
        *   **Response Body Values:** `pm.expect(jsonData.name).to.eql("Expected Item Name");`, `pm.expect(jsonData.data[0].id).to.exist;`
        *   **Headers:** `pm.response.to.have.header('Content-Type', 'application/json; charset=utf-8');`
        *   **Response Times:** `pm.expect(pm.response.responseTime).to.be.below(500);` (assert response time is less than 500ms).

*   **Newman:**
    1.  **Installation:** Install Newman globally via npm:
        ```bash
        npm install -g newman
        ```
    2.  **Export Collection and Environment:** Export your Postman Collection (as JSON) and the relevant Postman Environment (as JSON).
    3.  **Run from CLI:** Execute the collection using Newman:
        ```bash
        newman run "AppGini_API.postman_collection.json" -e "Local_Environment.postman_environment.json"
        ```
        Newman can generate various report formats (HTML, JUnit) and is ideal for integrating API tests into CI/CD pipelines (e.g., Jenkins, GitLab CI, GitHub Actions).

*   **Key Test Cases for Postman/Newman:**
    *   **Authentication (JWT):**
        1.  **Login Success:** Request `/api/v1/login` with valid admin/user credentials. Assert HTTP `200 OK`. Assert the response body contains a `token` (or `access_token`). Extract and store this token in an environment variable `{{jwtToken}}` for subsequent requests (`pm.environment.set("jwtToken", jsonData.token);`).
        2.  **Login Failure:** Request `/api/v1/login` with invalid credentials. Assert HTTP `401 Unauthorized`.
        3.  **Access Protected Route (No Token):** Attempt to access a protected endpoint (e.g., `GET /api/v1/tu_tabla`) without an `Authorization` header. Assert HTTP `401 Unauthorized`.
        4.  **Access Protected Route (Invalid/Expired Token):** Attempt to access a protected endpoint with a deliberately malformed or expired JWT. Assert HTTP `401 Unauthorized`.
        5.  **Access Protected Route (Valid Token):** Access a protected endpoint using the valid `{{jwtToken}}`. Assert HTTP `200 OK` (or other success codes like `204` for DELETE).
    *   **GET Operations (List & Specific Item):**
        1.  **List with Pagination:** `GET /api/v1/tu_tabla?limit=5&offset=0`. Assert `200 OK`, correct number of items in `data` array, presence and correctness of `pagination` object fields.
        2.  **List with Filters:** `GET /api/v1/tu_tabla?filters=field_name:eq:value`. Assert `200 OK` and that returned items match the filter criteria.
        3.  **List with Invalid Filters:** `GET /api/v1/tu_tabla?filters=invalid_field:eq:value` or invalid syntax. Assert `400 Bad Request`.
        4.  **List with Sorting:** `GET /api/v1/tu_tabla?order=name,desc`. Assert `200 OK` and that results are sorted correctly.
        5.  **Get Specific Item:** After creating an item (see below), `GET /api/v1/tu_tabla/{{createdItemId}}`. Assert `200 OK` and that the response matches the created item's data.
        6.  **Get Non-Existent Item:** `GET /api/v1/tu_tabla/999999` (an ID that doesn't exist). Assert `404 Not Found`.
    *   **CRUD Operations Workflow (Create, Read, Update, Delete):**
        *   **Create (POST):**
            1.  POST valid data to `/api/v1/tu_tabla`. Assert `201 Created`. Assert response body contains the created item with an `id`. Store this `id` as `{{createdItemId}}`. Check `Location` header.
            2.  POST data with missing required fields. Assert `422 Unprocessable Entity` (or `400`) and that the `details` in the error response correctly indicate the missing fields.
            3.  Attempt POST without sufficient permissions (e.g., as a read-only user if such roles are defined). Assert `403 Forbidden`.
        *   **Update (PUT/PATCH):**
            1.  PUT (full update) or PATCH (partial update) `/api/v1/tu_tabla/{{createdItemId}}` with valid data. Assert `200 OK`. Assert response body reflects the updates.
            2.  Attempt to GET `/api/v1/tu_tabla/{{createdItemId}}` again and verify updates persisted.
            3.  PUT/PATCH with invalid data (e.g., wrong data type for a field). Assert `422 Unprocessable Entity` (or `400`).
            4.  PUT/PATCH a non-existent ID. Assert `404 Not Found`.
            5.  Attempt PUT/PATCH without permission or on a record not owned by the user (if owner-only permissions apply). Assert `403 Forbidden`.
        *   **Delete (DELETE):**
            1.  DELETE `/api/v1/tu_tabla/{{createdItemId}}`. Assert `204 No Content` (or `200 OK` with a success message).
            2.  Attempt to GET `/api/v1/tu_tabla/{{createdItemId}}` after deletion. Assert `404 Not Found`.
            3.  Attempt to DELETE a non-existent ID. Assert `404 Not Found`.
            4.  Attempt DELETE without permission. Assert `403 Forbidden`.
    *   **Data Passing Between Requests:** Use `pm.environment.set("variableName", value)` in the "Tests" script of one request to save data (like a newly created item's ID or a JWT) and `{{variableName}}` in subsequent requests to use that data.

**9.5. Staging Environment**

*   **Purpose:** A dedicated, pre-production environment that mirrors the production setup as closely as possible. It's used for final testing and validation before deploying new API versions or changes live.
*   **Setup:**
    *   **Infrastructure:** Should ideally use the same operating system, PHP version, web server software (Apache/Nginx) and configuration, database type and version, and other dependencies as the production environment.
    *   **Database:** Use a separate database instance for staging. This database can be populated with:
        *   A subset of anonymized production data.
        *   Realistic sample data generated specifically for testing.
        *   Data from a recent backup of production (if data privacy and size allow, and it's properly sanitized).
    *   **Configuration:** API configuration (e.g., database credentials, JWT secrets, external service URLs) should be specific to the staging environment.
*   **Benefits:**
    *   **Manual Exploratory Testing:** Allows developers, QAs, and even stakeholders to perform manual testing of the API using tools like Postman or by connecting a frontend application to the staging API.
    *   **Full Test Suite Execution:** Run the complete suite of automated tests (Postman/Newman) against the staging environment to catch any issues related to the integrated system.
    *   **User Acceptance Testing (UAT):** Product owners or key users can perform UAT on staging to ensure the API meets business requirements.
    *   **Identify Environment-Specific Issues:** Helps uncover problems that might only appear in a production-like environment (e.g., configuration discrepancies, network issues, permission problems).
    *   **Performance Testing (Optional):** The staging environment can sometimes be used for preliminary performance or load testing if it has comparable resources to production.
*   **Deployment:** Ideally, deployment to the staging environment should be automated through a CI/CD (Continuous Integration/Continuous Deployment) pipeline. After code changes are merged and pass initial tests, the CI/CD system can automatically deploy the build to staging.

**9.6. Test Data Management**

Managing test data effectively is crucial for reliable and repeatable automated tests.

*   **Strategies:**
    *   **Database Seeding/Reset:** Before running a test suite (especially for integration and E2E tests), the test database should be reset to a known, consistent state. This can be done by:
        *   Running SQL scripts to drop and recreate tables, then insert predefined test data.
        *   Using database migration and seeding tools.
    *   **Transactional Tests (PHPUnit):** For PHPUnit integration tests that interact with the database, wrap each test method in a database transaction. Start the transaction in `setUp()` and roll it back in `tearDown()`. This ensures that changes made by one test do not affect others. (Requires database support for transactions, e.g., InnoDB in MySQL).
    *   **API-Based Data Setup/Cleanup (Postman/Newman):**
        *   **Setup:** Some tests might require specific data to exist. Use "setup" requests at the beginning of a Postman collection run (or within `pre-request` scripts) to create necessary prerequisite data via API calls.
        *   **Teardown:** Use "teardown" requests at the end of a collection run (or in the "Tests" script of the last relevant request, or using `pm.test()` with `after()` in more recent Postman versions) to delete any data created specifically for that test run. This helps keep the test environment clean.
    *   **Unique Data Generation:** For tests that create resources, generate unique data (e.g., appending a timestamp or random string to names/emails) to avoid conflicts if tests are run multiple times or in parallel without a full database reset.

By combining unit tests (PHPUnit), comprehensive end-to-end API tests (Postman/Newman), and a well-maintained staging environment, the project can achieve a high level of quality and reduce the risk of deploying faulty code to production.
