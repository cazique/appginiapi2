**Section 8: Practical Consumption Examples**

This section provides practical examples of how to consume the AppGini REST API using `curl` for command-line interaction and JavaScript's `fetch` API for web frontend integration. These examples assume the API is structured and behaves as outlined in the previous sections.

**8.1. `curl` Examples**

`curl` is a versatile command-line tool for transferring data with URLs, making it excellent for testing and interacting with APIs directly from the terminal.

*   **API Base URL Placeholder:** `YOUR_API_BASE_URL` (e.g., `http://localhost/your_appgini_dir/api/v1` for local development, or `https://yourdomain.com/api/v1` for production).
*   **Resource Name Placeholder:** `tu_tabla` (representing a generic AppGini table name, replace with actual table names like `products`, `orders`, etc.).
*   **JWT Token Placeholder:** `YOUR_JWT_TOKEN` (this must be replaced with a valid JWT obtained from the `/login` endpoint).

**8.1.1. `GET /api/v1/tu_tabla` (List Items)**

This command retrieves a list of items from `tu_tabla`, applying pagination, sorting, and filtering.

*   **Command:**
    ```bash
    curl -X GET "YOUR_API_BASE_URL/tu_tabla?limit=2&offset=0&order=name,asc&filters=status:eq:active" \
         -H "Authorization: Bearer YOUR_JWT_TOKEN" \
         -H "Content-Type: application/json"
    ```
    *   `-X GET`: Specifies the HTTP GET method.
    *   URL parameters:
        *   `limit=2`: Requests a maximum of 2 items.
        *   `offset=0`: Starts from the beginning of the list.
        *   `order=name,asc`: Sorts results by the `name` field in ascending order.
        *   `filters=status:eq:active`: Filters items where the `status` field is equal to `active`.
    *   `-H "Authorization: Bearer YOUR_JWT_TOKEN"`: Provides the JWT for authentication. **Replace `YOUR_JWT_TOKEN` with an actual token.**
    *   `-H "Content-Type: application/json"`: While not strictly necessary for GET requests, it's good practice to indicate the client can handle JSON (though `Accept` header is more accurate for this).

*   **Expected JSON Response (example based on OpenAPI spec from Section 7):**
    ```json
    {
      "pagination": {
        "total_records": 50,
        "limit": 2,
        "offset": 0,
        "current_page": 1,
        "total_pages": 25,
        "next_offset": 2
      },
      "data": [
        {
          "id": 10,
          "name": "Active Item Alpha",
          "description": "An active item for demonstration.",
          "status": "active",
          "created_by": "admin",
          "date_created": "2023-10-26T10:00:00Z",
          "last_modified_by": "admin",
          "last_modified_on": "2023-10-26T10:00:00Z"
        },
        {
          "id": 15,
          "name": "Active Item Beta",
          "description": "Another active item.",
          "status": "active",
          "created_by": "admin",
          "date_created": "2023-10-27T11:00:00Z",
          "last_modified_by": "admin",
          "last_modified_on": "2023-10-27T11:00:00Z"
        }
      ]
    }
    ```
    **Note:** The actual `total_records` and `data` content will depend on your database. The `status` field is assumed to be part of your `tu_tabla` schema and filterable.

**8.1.2. `POST /api/v1/tu_tabla` (Create Item)**

This command creates a new item in `tu_tabla`.

*   **Command:**
    ```bash
    curl -X POST "YOUR_API_BASE_URL/tu_tabla" \
         -H "Authorization: Bearer YOUR_JWT_TOKEN" \
         -H "Content-Type: application/json" \
         -d '{
              "name": "New Item via curl",
              "description": "This item was created using a curl command.",
              "some_other_field": "Value for other field"
            }'
    ```
    *   `-X POST`: Specifies the HTTP POST method.
    *   `-H "Content-Type: application/json"`: Indicates that the request body is JSON.
    *   `-d '{...}'`: Contains the JSON data for the new item. The fields (`name`, `description`, `some_other_field`) should match the writable fields defined in your `NewTuTablaItem` schema (Section 7).

*   **Expected JSON Response (HTTP 201 Created):**
    ```json
    {
      "id": 101,
      "name": "New Item via curl",
      "description": "This item was created using a curl command.",
      "some_other_field": "Value for other field",
      "created_by": "current_user_id",
      "date_created": "2023-10-28T14:30:00Z",
      "last_modified_by": "current_user_id",
      "last_modified_on": "2023-10-28T14:30:00Z"
    }
    ```
    *   The server should respond with HTTP status `201 Created`.
    *   The response body should contain the newly created resource, including server-assigned values like `id`, `date_created`, etc.
    *   A `Location` header pointing to the new resource (e.g., `Location: YOUR_API_BASE_URL/tu_tabla/101`) should also be returned.

*   **Example `curl` for a Validation Error (if `name` is required but not provided):**
    ```bash
    curl -X POST "YOUR_API_BASE_URL/tu_tabla" \
         -H "Authorization: Bearer YOUR_JWT_TOKEN" \
         -H "Content-Type: application/json" \
         -d '{"description": "Attempting to create with missing name."}'
    ```
*   **Expected Validation Error Response (HTTP 422 Unprocessable Entity or 400 Bad Request):**
    ```json
    {
      "error": true,
      "message": "Validation failed. Please check the provided data.",
      "code": "VALIDATION_ERROR",
      "details": {
        "name": ["The name field is required."]
      }
    }
    ```

**8.2. JavaScript `fetch` Examples**

The `fetch` API is the standard way to make HTTP requests in modern web browsers. These examples use `async/await` for cleaner asynchronous code.

**8.2.1. Obtaining a JWT Token (`/api/v1/login`)**

This function sends a POST request to the `/login` endpoint to exchange user credentials for a JWT.

```javascript
async function loginAndGetToken(apiUrl, username, password) {
  const loginUrl = `${apiUrl}/login`; // Assuming /login is under your API base URL

  try {
    const response = await fetch(loginUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json' // Client expects JSON response
      },
      body: JSON.stringify({ username, password })
    });

    const responseData = await response.json(); // Always try to parse JSON, even for errors

    if (!response.ok) {
      // response.status gives the HTTP status code (e.g., 401 for bad credentials)
      console.error(`Login failed with status ${response.status}:`, responseData.message || 'Unknown error');
      // You could throw an error here or return a specific error object
      // throw new Error(responseData.message || `Login failed: ${response.status}`);
      return null;
    }

    console.log('Login successful:', responseData);
    // Assuming the token is returned in a field named 'access_token' or 'token'
    // based on Section 2.2 example: { "access_token": "your.jwt.here", "expires_in": 3600 }
    return responseData.access_token || responseData.token;
  } catch (error) {
    // This catches network errors or issues with response.json() if not JSON
    console.error('Error during login request:', error);
    return null;
  }
}

// --- Usage Example ---
/*
const API_BASE_URL = 'http://localhost/your_appgini_dir/api/v1'; // Or your production URL
const appUsername = 'your_appgini_username'; // Replace with actual username
const appPassword = 'your_appgini_password'; // Replace with actual password

(async () => {
  console.log(`Attempting to login user: ${appUsername} at ${API_BASE_URL}/login`);
  const token = await loginAndGetToken(API_BASE_URL, appUsername, appPassword);

  if (token) {
    console.log('Received JWT:', token);
    // In a real application, store this token securely for subsequent API calls
    // For example, using localStorage (be mindful of XSS if not careful):
    // localStorage.setItem('jwtToken', token);
  } else {
    console.log('Failed to obtain JWT.');
  }
})();
*/
```
*   **Important:** In a real web application, the obtained JWT should be stored securely (e.g., in `localStorage`, `sessionStorage`, or an HttpOnly cookie if using server-side sessions that then manage the API token). For SPAs, `localStorage` is common, but be aware of XSS risks; ensure proper input sanitization and output encoding throughout your frontend application.

**8.2.2. Making a Protected `GET` Request (`/api/v1/tu_tabla`)**

This function demonstrates how to use a previously obtained JWT to make an authenticated GET request.

```javascript
async function fetchDataWithToken(apiUrl, resourcePathWithQuery, jwtToken) {
  const fullUrl = `${apiUrl}/${resourcePathWithQuery}`;

  try {
    const response = await fetch(fullUrl, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${jwtToken}`, // Crucial for sending the JWT
        'Accept': 'application/json' // Client expects JSON response
      }
    });

    const responseData = await response.json(); // Always try to parse JSON

    if (!response.ok) {
      console.error(`Failed to fetch ${resourcePathWithQuery} with status ${response.status}:`, responseData.message || 'Unknown error');
      if (response.status === 401) {
        // Handle unauthorized access specifically
        console.error('Authorization failed. Token might be expired or invalid. Please login again.');
        // In a real app, you might trigger a logout or token refresh mechanism here.
      }
      return null;
    }

    console.log(`Data from ${resourcePathWithQuery}:`, responseData);
    return responseData;
  } catch (error) {
    // Catches network errors or issues with response.json() if not JSON
    console.error(`Error fetching ${resourcePathWithQuery}:`, error);
    return null;
  }
}

// --- Usage Example (assuming 'token' was obtained and stored) ---
/*
const API_BASE_URL = 'http://localhost/your_appgini_dir/api/v1';
// const storedToken = localStorage.getItem('jwtToken'); // Retrieve stored token
const storedToken = 'YOUR_JWT_TOKEN_FROM_LOGIN'; // Replace with an actual token for testing

(async () => {
  if (storedToken) {
    console.log('Using stored JWT for data fetching:', storedToken);
    // Example: Fetch first 5 items from 'tu_tabla', sorted by name
    const resource = 'tu_tabla?limit=5&order=name,desc';
    const itemsData = await fetchDataWithToken(API_BASE_URL, resource, storedToken);

    if (itemsData && itemsData.data) {
      console.log('Successfully fetched items:', itemsData.data);
      // Process the itemsData.data array here
      itemsData.data.forEach(item => {
        console.log(`Item ID: ${item.id}, Name: ${item.name}`);
      });
    } else {
      console.log('Could not fetch items or no data returned.');
    }
  } else {
    console.log('No JWT token available. Please login first.');
    // Redirect to login page or prompt user to login
  }
})();
*/
```
*   The key here is the `Authorization: Bearer ${jwtToken}` header, which transmits the JWT to the server for authentication.
*   Proper error handling, especially for `401 Unauthorized` responses, is important. A `401` might mean the token has expired, prompting the application to request a new token (if refresh tokens are implemented, as mentioned in Section 2.5) or require the user to log in again.

These examples provide a solid foundation for developers to start consuming the API using common tools and techniques. They illustrate the request structure, authentication mechanism, and expected response formats.
