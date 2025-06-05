**Section 7: Automated Documentation (OpenAPI/Swagger)**

This section details the use of the OpenAPI Specification (OAS) for documenting the AppGini REST API. It includes example OpenAPI (YAML) snippets for key routes and discusses tools for generating, validating, and rendering this documentation.

**7.1. Introduction to OpenAPI**

*   **What is OpenAPI Specification (OAS)?**
    The OpenAPI Specification is a standard, language-agnostic interface description for RESTful APIs. It allows both humans (developers, testers, product managers) and computers (client SDK generators, testing tools, documentation generators) to discover and understand the capabilities of an API without needing access to its source code, additional documentation, or by inspecting network traffic. An OpenAPI document describes the API's endpoints, operations for each endpoint, operation parameters (input and output), authentication methods, and other metadata.

*   **Benefits of Using OpenAPI:**
    *   **Interactive API Documentation:** Enables the automatic generation of user-friendly, interactive HTML documentation using tools like Swagger UI or ReDoc. Developers can explore API endpoints and even try them out directly from their browser.
    *   **Client SDK Generation:** Facilitates the generation of client libraries (SDKs) in various programming languages (e.g., Java, Python, JavaScript, C#), significantly speeding up client-side development.
    *   **API Testing and Validation:** OpenAPI definitions can be used by tools to automate API testing, ensuring that the API behaves as specified. It can also be used to validate incoming requests against the defined schemas.
    *   **Single Source of Truth:** The OpenAPI document serves as the canonical contract for the API. Any changes to the API should be reflected in this document, ensuring all stakeholders are working with the same understanding.
    *   **Design-First or Code-First:** Supports both design-first (define the API in OpenAPI, then implement) and code-first (generate OpenAPI from code annotations, though less applicable to this project's proposed structure) approaches.

*   **YAML vs. JSON:**
    OpenAPI definitions can be written in either YAML or JSON. YAML is often preferred for its readability and ability to include comments, making it easier for humans to write and maintain, especially for larger definitions. JSON is more verbose but equally valid. This section will use YAML for examples.

**7.2. Core OpenAPI Concepts in Snippets**

The provided OpenAPI snippets will utilize the following core concepts:

*   `openapi`: Specifies the version of the OpenAPI Specification being used (e.g., `3.0.3` or `3.1.0`).
*   `info`: Contains general information about the API, such as its `title`, `version` (this is the API's own version, distinct from the OAS version), and `description`.
*   `servers`: An array of server objects providing the base URL(s) for the API. This allows specifying different URLs for development, staging, and production environments.
*   `paths`: Defines the available API endpoints (e.g., `/tu_tabla`, `/tu_tabla/{itemId}`) and the HTTP methods (operations) supported by each endpoint (e.g., `get`, `post`, `put`, `delete`).
*   `components`: A section for defining reusable objects that can be referenced throughout the API definition. This helps avoid redundancy and keep the definition organized.
    *   `schemas`: Defines the data models (schemas) for request bodies and response bodies using JSON Schema.
    *   `parameters`: Defines reusable parameters that can appear in operations (e.g., query parameters like `limit`, path parameters like `itemId`, header parameters).
    *   `responses`: Defines reusable response structures (e.g., a standard error response).
    *   `securitySchemes`: Defines the authentication and authorization methods used by the API (e.g., JWT Bearer authentication).
*   `security`: Specifies global security requirements that apply to all operations unless overridden at the operation level.

**7.3. OpenAPI Snippet (YAML)**

The following YAML snippet provides an OpenAPI definition for `GET /api/v1/tu_tabla` (listing items with pagination, filtering, sorting) and `POST /api/v1/tu_tabla` (creating a new item). `tu_tabla` is used as a placeholder for a generic AppGini table name (e.g., "items", "products", "orders").

```yaml
openapi: 3.0.3
info:
  title: AppGini REST API
  description: Exposes AppGini generated tables via a RESTful interface. This API allows for CRUD operations on tables, adhering to AppGini permissions and validation rules.
  version: v1.0.0 # Version of this API, not OpenAPI spec version
  contact:
    name: API Support
    url: https://yourdomain.com/support
    email: support@yourdomain.com
  license:
    name: Apache 2.0 # Or your chosen license
    url: https://www.apache.org/licenses/LICENSE-2.0.html

servers:
  - url: https://api.yourdomain.com/api/v1 # Example production server
    description: Production Server
  - url: http://localhost/appgini_project/api/v1 # Adjust to your local AppGini project path
    description: Development Server

components:
  schemas:
    # Schema for a single item from 'tu_tabla'
    TuTablaItem:
      type: object
      properties:
        id:
          type: integer
          format: int64 # Use int64 for potentially large primary keys
          description: Unique identifier for the item. This is typically the auto-incrementing primary key from the AppGini table.
          readOnly: true # Client cannot set this value, it's server-assigned
          example: 123
        # --- Common AppGini fields (examples, adjust to your table) ---
        name: # Assuming a 'name' field exists
          type: string
          description: Name or primary display field of the item.
          example: "Sample Item Name"
        description: # Assuming a 'description' field exists
          type: string
          nullable: true # Allow null if the field can be empty
          description: Detailed description of the item.
          example: "This is a longer description for the sample item."
        # AppGini often includes metadata fields like created_by, date_created, etc.
        # These are conceptual. Actual field names from AppGini should be used.
        created_by:
          type: string
          nullable: true
          description: MemberID of the user who created the record.
          readOnly: true
          example: "admin"
        date_created:
          type: string
          format: date-time # ISO 8601 date-time
          description: Timestamp of when the item was created.
          readOnly: true
          example: "2023-03-15T10:30:00Z"
        last_modified_by:
          type: string
          nullable: true
          description: MemberID of the user who last modified the record.
          readOnly: true
          example: "editor_user"
        last_modified_on:
          type: string
          format: date-time
          description: Timestamp of when the item was last updated.
          readOnly: true
          example: "2023-03-16T14:45:10Z"
      # 'required' array lists fields that are guaranteed to be present in a response for this item.
      # For creation, 'required' is defined in NewTuTablaItem.
      required:
        - id
        - name
        # Add other fields that are always present in the AppGini table's output.

    # Schema for the request body when creating a 'tu_tabla' item
    NewTuTablaItem:
      type: object
      description: Data required to create a new item in 'tu_tabla'.
      properties:
        name: # Assuming a 'name' field exists and is writable
          type: string
          description: Name or primary display field of the item.
          example: "New Item Created via API"
        description: # Assuming a 'description' field exists and is writable
          type: string
          nullable: true
          description: Detailed description of the item.
          example: "This is the description for the new item being created."
        # Add other writable fields from your AppGini table here.
        # Example: a category_id if it's a lookup field
        # category_id:
        #   type: integer
        #   description: ID of the related category.
        #   example: 5
      # 'required' array lists fields that MUST be provided by the client when creating an item.
      # This should align with AppGini's "Required" field settings.
      required:
        - name
        # Add other fields that are mandatory for creation in AppGini.

    # Standard Error Response (as defined in Section 4)
    ErrorResponse:
      type: object
      properties:
        error:
          type: boolean
          description: Indicates if the response is an error.
          example: true
        message:
          type: string
          description: Human-readable error message.
          example: "Validation failed."
        code:
          type: string
          nullable: true
          description: Machine-readable error code.
          example: "VALIDATION_ERROR"
        details:
          type: object
          nullable: true
          description: Object containing specific error details, often used for validation errors where keys are field names.
          additionalProperties:
            type: array
            items:
              type: string
          example: {"name": ["Name field cannot be empty."], "email": ["Must be a valid email address."]}
      required:
        - error
        - message

    # Paginated Response for GET list operations (as defined in Section 3)
    PaginatedTuTablaItems:
      type: object
      properties:
        pagination:
          type: object
          properties:
            total_records:
              type: integer
              description: Total number of records matching the query.
              example: 1250
            limit:
              type: integer
              description: The limit for records per page used in this request.
              example: 20
            offset:
              type: integer
              description: The offset used in this request.
              example: 40
            current_page:
              type: integer
              description: The current page number.
              example: 3
            total_pages:
              type: integer
              description: Total number of pages available.
              example: 63
            next_offset:
              type: integer
              nullable: true
              description: Offset for the next page, if available.
              example: 60
            prev_offset:
              type: integer
              nullable: true
              description: Offset for the previous page, if available.
              example: 20
          required:
            - total_records
            - limit
            - offset
            - current_page
            - total_pages
        data:
          type: array
          description: Array of 'tu_tabla' items for the current page.
          items:
            $ref: '#/components/schemas/TuTablaItem' # Reference to the single item schema

  parameters: # Reusable parameters
    LimitParam:
      name: limit
      in: query
      description: Maximum number of records to return per page.
      required: false
      schema:
        type: integer
        default: 20
        minimum: 1
        maximum: 100 # Enforce a max limit
    OffsetParam:
      name: offset
      in: query
      description: Number of records to skip (for pagination).
      required: false
      schema:
        type: integer
        default: 0
        minimum: 0
    OrderParam:
      name: order
      in: query
      description: "Sort order for results. Format: `fieldName[,asc|desc]`. Multiple fields separated by semicolon (`;`). Example: `name,asc;date_created,desc`"
      required: false
      schema:
        type: string
        example: "name,asc;date_created,desc"
    FiltersParam:
      name: filters
      in: query
      description: "Filters to apply. Format: `field:operator:value`, comma-separated. Supported operators: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`, `notin`, `isnull`, `isnotnull`. Example: `status:eq:active,price:gte:100`"
      required: false
      schema:
        type: string
        example: "status:eq:active,name:like:Gadget%"
    QParam:
      name: q
      in: query
      description: Global textual search term across predefined searchable fields for the table.
      required: false
      schema:
        type: string
        example: "durable waterproof"
    ItemIdParam:
      name: itemId # Consistent name for item ID path parameter
      in: path
      required: true
      description: The unique identifier of the specific item.
      schema:
        type: integer
        format: int64
        example: 42

  securitySchemes:
    BearerAuth: # Defines JWT Bearer token authentication
      type: http
      scheme: bearer
      bearerFormat: JWT # Indicates the format of the bearer token
      description: "JWT Authorization header using the Bearer scheme. Example: `Authorization: Bearer <YOUR_TOKEN>`"

security: # Global security requirement: all paths require BearerAuth unless overridden
  - BearerAuth: []

paths:
  /tu_tabla: # Path for the 'tu_tabla' resource collection
    get:
      summary: List 'tu_tabla' items
      description: Retrieves a paginated list of items from 'tu_tabla', allowing for filtering and sorting.
      operationId: listTuTablaItems # Unique ID for the operation
      tags:
        - TuTabla # Tag for grouping operations related to 'tu_tabla'
      parameters:
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - $ref: '#/components/parameters/OrderParam'
        - $ref: '#/components/parameters/FiltersParam'
        - $ref: '#/components/parameters/QParam'
      responses:
        '200': # Successful response
          description: A paginated list of 'tu_tabla' items.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/PaginatedTuTablaItems'
              examples:
                default: # Example of a 200 response
                  value:
                    pagination:
                      total_records: 2
                      limit: 10
                      offset: 0
                      current_page: 1
                      total_pages: 1
                    data:
                      - id: 1
                        name: "First Item"
                        description: "Description of the first item."
                        created_by: "admin"
                        date_created: "2023-10-26T10:00:00Z"
                        last_modified_by: "admin"
                        last_modified_on: "2023-10-26T10:00:00Z"
                      - id: 2
                        name: "Second Item"
                        description: "Description of the second item."
                        created_by: "user1"
                        date_created: "2023-10-27T11:00:00Z"
                        last_modified_by: "user1"
                        last_modified_on: "2023-10-27T11:00:00Z"
        '400': # Bad Request
          description: Bad Request (e.g., invalid parameter format for filters or sorting).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                error: true
                message: "Invalid filter format provided."
                code: "INVALID_PARAMETER"
        '401': # Unauthorized
          description: Unauthorized (JWT token missing, malformed, or invalid).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                error: true
                message: "Authentication token is required."
                code: "UNAUTHENTICATED"
        '403': # Forbidden
          description: Forbidden (User authenticated but does not have 'view' permission for this table).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                error: true
                message: "You do not have permission to view this resource."
                code: "FORBIDDEN"
        '500': # Internal Server Error
          description: Internal Server Error (e.g., database connection issue).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                error: true
                message: "An unexpected internal error occurred."
                code: "INTERNAL_SERVER_ERROR"

    post:
      summary: Create a 'tu_tabla' item
      description: Adds a new item to the 'tu_tabla' collection.
      operationId: createTuTablaItem
      tags:
        - TuTabla
      requestBody:
        description: Data for the new item to be created in 'tu_tabla'.
        required: true
        content:
          application/json: # Expecting JSON request body
            schema:
              $ref: '#/components/schemas/NewTuTablaItem' # Reference to the creation schema
            examples:
              default: # Example of a request body
                value:
                  name: "Shiny New Gadget"
                  description: "This gadget is brand new and does amazing things."
                  # category_id: 7 # Example of another writable field
      responses:
        '201': # Item Created
          description: Item created successfully. The created item is returned.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TuTablaItem' # Returns the full item schema, including ID and timestamps
              example: # Example of a 201 response body
                id: 101
                name: "Shiny New Gadget"
                description: "This gadget is brand new and does amazing things."
                created_by: "current_user_id"
                date_created: "2023-10-28T12:00:00Z"
                last_modified_by: "current_user_id"
                last_modified_on: "2023-10-28T12:00:00Z"
          headers:
            Location: # Location header pointing to the new resource
              description: URL of the newly created resource.
              schema:
                type: string
                format: uri # Using uri format for better validation
                example: "/api/v1/tu_tabla/101"
        '400': # Bad Request (often validation errors)
          description: Bad Request (e.g., validation error due to missing required fields or invalid data types).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example: # Example of a validation error response
                error: true
                message: "Validation failed. Please check the provided data."
                code: "VALIDATION_ERROR"
                details:
                  name: ["The name field is required."]
                  # category_id: ["Category ID must be an integer."]
        '401': # Unauthorized
          description: Unauthorized (JWT token missing or invalid).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '403': # Forbidden
          description: Forbidden (User does not have 'insert' permission for this table).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '409': # Conflict
          description: Conflict (e.g., trying to create an item that violates a unique constraint, if applicable beyond PK).
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                error: true
                message: "An item with this name already exists."
                code: "RESOURCE_CONFLICT"
        '500': # Internal Server Error
          description: Internal Server Error.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /tu_tabla/{itemId}: # Path for operations on a specific 'tu_tabla' item
    parameters: # Parameters applicable to all operations under this path
      - $ref: '#/components/parameters/ItemIdParam'
    get:
      summary: Get a 'tu_tabla' item by ID
      description: Retrieves details of a specific item from 'tu_tabla' using its unique ID.
      operationId: getTuTablaItemById
      tags: [TuTabla]
      responses:
        '200':
          description: Details of the specific 'tu_tabla' item.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TuTablaItem'
        '401':
          description: Unauthorized.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '403':
          description: Forbidden (User does not have 'view' permission or owner-only view restriction).
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '404':
          description: Item not found.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '500':
          description: Internal Server Error.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
    put:
      summary: Update a 'tu_tabla' item
      description: Updates an existing item in 'tu_tabla' using its unique ID.
      operationId: updateTuTablaItemById
      tags: [TuTabla]
      requestBody:
        description: Data to update for the existing item. All writable fields can be provided.
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/NewTuTablaItem' # Can often reuse the creation schema, or define a specific UpdateTuTablaItem schema if fields differ (e.g. some fields only settable on create)
      responses:
        '200':
          description: Item updated successfully. The updated item is returned.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TuTablaItem'
        '400':
          description: Bad Request (e.g., validation error).
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '401':
          description: Unauthorized.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '403':
          description: Forbidden (User does not have 'edit' permission or owner-only edit restriction).
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '404':
          description: Item not found.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '500':
          description: Internal Server Error.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
    delete:
      summary: Delete a 'tu_tabla' item
      description: Deletes an existing item from 'tu_tabla' using its unique ID.
      operationId: deleteTuTablaItemById
      tags: [TuTabla]
      responses:
        '204': # No Content - successful deletion
          description: Item deleted successfully. No content returned.
        '401':
          description: Unauthorized.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '403':
          description: Forbidden (User does not have 'delete' permission or owner-only delete restriction).
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '404':
          description: Item not found.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}
        '500':
          description: Internal Server Error.
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse'}}}

```

**7.4. Tools for OpenAPI Documentation**

A variety of tools can assist in working with OpenAPI definitions:

*   **Generation/Writing:**
    *   **Manual Creation:** Writing YAML or JSON directly in a text editor is common, especially with good editor plugins that offer syntax highlighting, autocompletion (based on the OpenAPI schema), and basic validation (e.g., VS Code with OpenAPI extensions).
    *   **Swagger Editor:** (editor.swagger.io) An online editor provided by SmartBear (the stewards of Swagger/OpenAPI). It offers real-time validation, a split view showing the definition and rendered documentation, and import/export capabilities. Docker images are also available for local hosting.
    *   **Stoplight Studio:** A powerful visual editor for designing and documenting APIs using OpenAPI. It offers a more graphical approach compared to raw YAML/JSON editing.
    *   **Code Annotations (Framework Dependent):** For some PHP frameworks (like Symfony with a specific bundle, or projects using attributes extensively), tools like `swagger-php` can generate OpenAPI definitions from code annotations (docblocks or PHP 8 attributes). Given the proposed simpler structure for this AppGini API project, manual or editor-based creation is likely more direct and manageable.

*   **Validation:**
    *   **Swagger Editor / Spectral:** The Swagger Editor includes built-in validation. Spectral is a flexible CLI-based linter for OpenAPI documents, allowing for custom rule sets to enforce style guides and best practices.
    *   **`openapi-spec-validator` (Python library):** A command-line tool and library for validating OpenAPI documents. Useful for integrating into CI/CD pipelines.
    *   **Online Validators:** Various websites offer quick OpenAPI validation by pasting or uploading the definition file.

*   **Rendering Interactive Documentation:**
    *   **Swagger UI:** (github.com/swagger-api/swagger-ui) Takes an OpenAPI document and generates beautiful, interactive HTML documentation. Users can explore endpoints, view schemas, and directly execute API calls from the browser. It's highly configurable and can be hosted statically or integrated into an application.
    *   **ReDoc:** (github.com/Redocly/redoc) Generates a clean, three-panel documentation layout that is often praised for its readability and professional appearance. It's less focused on the "try it out" feature compared to Swagger UI but excels at presenting information clearly.
    *   **Stoplight Elements:** (stoplight.io/open-source/elements) An open-source, embeddable documentation UI that can be integrated into existing websites or developer portals.

*   **Serving the OpenAPI File:**
    The `openapi.yaml` (or `openapi.json`) file itself should be made accessible via a URL from your API server (e.g., `/api/v1/openapi.yaml` or `/api/openapi.json`). This allows documentation tools like Swagger UI or ReDoc to fetch and render it. This can be achieved by:
    *   Placing the static file in a web-accessible directory.
    *   Creating a specific API endpoint that serves the content of the file.

**7.5. Integration Strategy**

*   **Maintenance:** The `openapi.yaml` file should be treated as a critical part of the API's codebase and version-controlled alongside it (e.g., in `/api/v1/openapi.yaml` or a central `/api/docs/` directory).
*   **Primary Method:** For this project, it is recommended to **manually create and maintain the core `openapi.yaml` file** using a text editor or a dedicated OpenAPI editor like Swagger Editor or Stoplight Studio. This provides direct control over the documentation content.
*   **Documentation UI:**
    *   **Option 1 (Simple):** Host the `openapi.yaml` file and direct users to public instances of Swagger Editor (by providing the URL to your YAML file) or other online viewers.
    *   **Option 2 (Integrated):** Download the Swagger UI or ReDoc distribution files and configure them to load your API's `openapi.yaml` file. These static HTML/JS/CSS files can then be served by your web server (Apache/Nginx) at a dedicated path, such as `/api/v1/docs/`. This provides a self-hosted documentation portal.
    *   **Example for self-hosting Swagger UI:**
        1.  Download Swagger UI dist.
        2.  Place it in a web-accessible path (e.g., `/api/v1/swagger-ui/`).
        3.  Modify `swagger-ui/index.html` or `swagger-initializer.js` to point `url` to your `/api/v1/openapi.yaml`.

By diligently maintaining an OpenAPI document and leveraging tools to render it, the API becomes significantly more accessible, understandable, and easier to integrate for developers.
