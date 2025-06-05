**Section 3: GET Operations: Pagination, Filters, and Sorting**

This section outlines the implementation details for handling `GET` requests, specifically focusing on how `GET.php` (or a more structured `GetController.php` as per the proposed architecture) will manage pagination, flexible filtering capabilities, and sorting of results. Clear URL parameter conventions are defined, along with an example demonstrating their usage.

**3.1. Parameter Conventions**

A consistent and clear set of query parameters is crucial for a usable API. The following conventions are proposed for `GET` requests:

*   **`limit`: Integer**
    *   Specifies the maximum number of records to return in a single response.
    *   Example: `limit=25`
    *   **Default:** If not provided, a sensible default (e.g., `20` or `50`) will be applied.
    *   **Maximum:** A maximum allowable limit (e.g., `100` or `200`) should be enforced to prevent server abuse and overly large responses. Requests exceeding this maximum can either be rejected with a `400 Bad Request` error or automatically capped at the maximum.

*   **`offset`: Integer**
    *   Specifies the number of records to skip from the beginning of the result set. Used in conjunction with `limit` for pagination.
    *   Example: `offset=50` (skips the first 50 records)
    *   **Default:** `0` if not provided.

*   **`order`: String**
    *   Determines the sorting order of the results.
    *   **Single field sorting:**
        *   `order=fieldName` (defaults to ascending order)
        *   `order=fieldName,asc` (explicitly ascending)
        *   `order=fieldName,desc` (explicitly descending)
    *   **Multiple field sorting:** Fields are separated by a semicolon (`;`).
        *   Example: `order=field1,asc;field2,desc` (Sort by `field1` ascending, then by `field2` descending).
    *   **Validation:** All field names provided in the `order` parameter must be validated against a whitelist of actual, sortable column names for the requested table to prevent SQL injection or errors. Invalid field names or sort directions should result in a `400 Bad Request` error or be ignored.

*   **`filters`: String**
    *   Provides a flexible mechanism for applying multiple filter criteria to the result set.
    *   **Format:** A comma-separated list of individual filter conditions, where each condition is `field:operator:value`.
        *   Example: `filters=status:eq:approved,date_created:gte:2023-01-01`
    *   **Supported Operators:**
        *   `eq`: Equals (e.g., `status:eq:active`)
        *   `neq`: Not equals (e.g., `priority:neq:low`)
        *   `gt`: Greater than (e.g., `price:gt:100`)
        *   `gte`: Greater than or equal to (e.g., `stock:gte:10`)
        *   `lt`: Less than (e.g., `age:lt:30`)
        *   `lte`: Less than or equal to (e.g., `discount:lte:0.15`)
        *   `like`: Partial string match (e.g., `name:like:%smith%`). Use with caution as leading wildcards (`%term`) can impact database performance. AppGini's `makeSafe()` should be used for the value if not using prepared statements, though prepared statements are superior.
        *   `in`: Value is in a comma-separated list (e.g., `id:in:1,2,3,4`). The list of values within the filter part should be further processed.
        *   `notin`: Value is not in a comma-separated list (e.g., `category_id:notin:10,11`).
        *   `isnull`: Field's value is NULL (e.g., `completed_at:isnull`). The value part is ignored for this operator.
        *   `isnotnull`: Field's value is not NULL (e.g., `assigned_to:isnotnull`). The value part is ignored.
    *   **Validation:**
        *   Field names must be validated against a whitelist of filterable fields for the table.
        *   Operators must be validated against the supported list.
        *   Values must be appropriately sanitized and used with prepared statements.
    *   **Example:** `filters=status:eq:approved,date_created:gte:2023-01-01,customer_name:like:%john%`

*   **`q`: String**
    *   A global search parameter for performing a simple, full-text-like search across a predefined set of fields for the table. This offers a user-friendly way to quickly search without constructing complex filter strings.
    *   Example: `q=search term`
    *   **Implementation:** The API would define which fields are included in this global search for each table (e.g., product name, description, SKU for a `products` table). The search term would typically be used in `LIKE '%term%'` clauses ORed together for these fields.

**3.2. Pagination (`limit` and `offset`)**

*   **Implementation in `GET.php` / `GetController.php`:**
    1.  Retrieve `limit` and `offset` values from the query parameters.
    2.  Apply default values if they are not provided (e.g., `limit = 20`, `offset = 0`).
    3.  Enforce the maximum limit (e.g., cap `limit` at `100`).
    4.  Sanitize both `limit` and `offset` to ensure they are non-negative integers.
    5.  In the SQL query construction, append `LIMIT ? OFFSET ?`.
    6.  Bind the sanitized `limit` and `offset` values as parameters in the prepared statement.

*   **Response Metadata:**
    To enable clients to effectively navigate paginated results, the JSON response must include pagination metadata.

    ```json
    {
      "pagination": {
        "total_records": 1250,
        "limit": 25,
        "offset": 50,
        "current_page": 3,
        "total_pages": 50,
        "next_offset": 75, // Optional: calculated as offset + limit if not last page
        "prev_offset": 25  // Optional: calculated as offset - limit if not first page
      },
      "data": [
        // ... array of records ...
      ]
    }
    ```
    *   `total_records`: Total number of records matching the filter criteria (before pagination).
    *   `limit`: The limit used for this request.
    *   `offset`: The offset used for this request.
    *   `current_page`: Calculated as `floor(offset / limit) + 1`.
    *   `total_pages`: Calculated as `ceil(total_records / limit)`.
    *   `next_offset`, `prev_offset`: Can be provided to make it easier for clients to request the next/previous set of records.

*   **Counting Total Records:**
    To provide `total_records`, a separate SQL query is required:
    `SELECT COUNT(*) FROM your_table WHERE ... (filters applied);`
    This query must use the exact same `WHERE` clause (derived from `filters` and `q` parameters) as the main data retrieval query. This count should be performed *before* applying `LIMIT` and `OFFSET` to the query that fetches the actual data.

**3.3. Filtering (`filters` and `q`)**

*   **Implementation in `GET.php` / `GetController.php`:**

    *   **`filters` parameter:**
        1.  Parse the `filters` string (e.g., split by comma for conditions, then by colon for parts).
        2.  For each filter condition (`field:operator:value`):
            *   **Validate Field Name:** Check the `field` against a pre-defined whitelist of filterable fields for the current `tableName`. This is a critical security measure.
            *   **Validate Operator:** Check the `operator` against the list of supported operators (eq, neq, gt, etc.).
            *   **Sanitize Value:** The `value` part must be treated as user input. While prepared statements are the primary defense, basic sanitization or type casting might be applied depending on the expected data type. For `in` or `notin` operators, the comma-separated list of values needs careful parsing and each value should be bound individually or as part of a correctly formatted list for the prepared statement.
            *   **Construct WHERE Clause:** Dynamically build the SQL `WHERE` clause segments. Use placeholders (`?`) for all values to be used with prepared statements.
                *   `status:eq:active`  -> `validated_status_field = ?` (binding: "active")
                *   `id:in:1,2,3`       -> `validated_id_field IN (?, ?, ?)` (bindings: 1, 2, 3) or use `FIND_IN_SET` if appropriate for the DB and value types, still with sanitization.
            *   **Date Range Handling:** If a field represents a date/datetime, `gte` and `lte` can be combined to form date ranges:
                *   `date_created:gte:2023-01-01,date_created:lte:2023-01-31` -> `validated_date_created_field >= ? AND validated_date_created_field <= ?`

    *   **`q` parameter (Global Search):**
        1.  Define a list of searchable fields for each table (e.g., `name`, `description`, `email`).
        2.  If the `q` parameter is present and not empty:
            *   Prepare the search term, typically by wrapping it with wildcards: `'%searchTerm%'`.
            *   Construct a `WHERE` clause segment like `(searchable_field1 LIKE ? OR searchable_field2 LIKE ? OR ...)`
            *   Add this segment to the main `WHERE` clause, combined with an `AND` if other filters are present.
            *   Ensure the search term is bound as a parameter in the prepared statement for each `LIKE` condition.

*   **Security:**
    *   **Field Name Whitelisting:** Crucially, all field names derived from `filters` and `order` parameters *must* be validated against a whitelist of allowed (and indexed) column names for the specific table being queried. This prevents SQL injection through manipulation of column names in the query structure (e.g., `filters=username:eq:(SELECT password FROM users WHERE id=1)` if not handled correctly, though prepared statements for values mitigate the value part).
    *   **Prepared Statements:** All filter values and `q` search terms must be bound to the SQL query using prepared statements to prevent SQL injection through data values.

**3.4. Sorting (`order`)**

*   **Implementation in `GET.php` / `GetController.php`:**
    1.  Parse the `order` parameter string.
        *   Split by semicolon (`;`) to get individual sort conditions if multiple exist.
        *   For each condition, split by comma (`,`) to separate the field name and sort direction (e.g., "fieldName,desc").
    2.  For each sort condition:
        *   **Validate Field Name:** Check the field name against a pre-defined whitelist of sortable fields for the current `tableName`.
        *   **Validate Direction:** Ensure the direction is either `ASC` or `DESC`. Default to `ASC` if the direction is missing or invalid.
    3.  Dynamically construct the `ORDER BY` clause of the SQL query.
        *   Example: `ORDER BY validated_field1 ASC, validated_field2 DESC`. If no valid `order` parameter is provided, a default order (e.g., by primary key) can be applied.

**3.5. Example Code Snippets (Conceptual PHP for `GetController.php`)**

```php
<?php
// File: api/v1/Controllers/GetController.php (Conceptual & Simplified)

// Assuming:
// - $this->db is a PDO instance.
// - $this->tableName is the AppGini table name being queried.
// - AppGiniHelper::getTableConfig($this->tableName) returns an array like:
//   [
//       'fields' => ['id', 'name', 'status', 'price', 'date_created', 'category_id', 'rating'], // All queryable fields
//       'filterable_fields' => ['name', 'status', 'price', 'date_created', 'category_id', 'rating'],
//       'sortable_fields' => ['name', 'status', 'price', 'date_created', 'rating'],
//       'searchable_fields' => ['name', 'description_field'] // For 'q' parameter
//   ]
// - Response::json() is a helper to send JSON responses.

class GetController {
    // ... constructor, other properties ...

    public function handleRequest(array $requestParams) {
        $tableConfig = AppGiniHelper::getTableConfig($this->tableName);
        $allowedFields = $tableConfig['fields']; // For SELECT clause, default to '*' or specific list
        $filterableFields = $tableConfig['filterable_fields'];
        $sortableFields = $tableConfig['sortable_fields'];
        $searchableFields = $tableConfig['searchable_fields'];

        // --- Pagination ---
        $limit = isset($requestParams['limit']) ? (int)$requestParams['limit'] : 20;
        $limit = max(1, min($limit, 100)); // Ensure positive and enforce max limit
        $offset = isset($requestParams['offset']) ? (int)$requestParams['offset'] : 0;
        $offset = max(0, $offset); // Ensure non-negative

        $whereClauses = [];
        $bindings = [];

        // --- Filtering ('filters' parameter) ---
        if (!empty($requestParams['filters'])) {
            $filters = explode(',', $requestParams['filters']);
            foreach ($filters as $filter) {
                $parts = explode(':', $filter, 3);
                if (count($parts) === 3) {
                    list($field, $operator, $value) = $parts;
                    if (in_array($field, $filterableFields)) {
                        $opMap = ['eq'=>'=', 'neq'=>'!=', 'gt'=>'>', 'gte'=>'>=', 'lt'=>'<', 'lte'=>'<=', 'like'=>'LIKE'];
                        if (isset($opMap[$operator])) {
                            $whereClauses[] = "`{$field}` {$opMap[$operator]} ?";
                            $bindings[] = ($operator === 'like') ? "%{$value}%" : $value;
                        } elseif ($operator === 'in' || $operator === 'notin') {
                            $inValues = explode(',', $value);
                            $placeholders = rtrim(str_repeat('?,', count($inValues)), ',');
                            $whereClauses[] = "`{$field}` " . ($operator === 'in' ? 'IN' : 'NOT IN') . " ({$placeholders})";
                            foreach ($inValues as $v) $bindings[] = $v;
                        } elseif ($operator === 'isnull' || $operator === 'isnotnull') {
                            $whereClauses[] = "`{$field}` IS " . ($operator === 'isnull' ? 'NULL' : 'NOT NULL');
                        }
                    }
                }
            }
        }

        // --- Global Search ('q' parameter) ---
        if (!empty($requestParams['q']) && !empty($searchableFields)) {
            $searchTerm = '%' . $requestParams['q'] . '%';
            $qSubClauses = [];
            foreach ($searchableFields as $sField) {
                if (in_array($sField, $filterableFields)) { // Ensure searchable field is also filterable
                    $qSubClauses[] = "`{$sField}` LIKE ?";
                    $bindings[] = $searchTerm;
                }
            }
            if (!empty($qSubClauses)) {
                $whereClauses[] = "(" . implode(" OR ", $qSubClauses) . ")";
            }
        }

        $sqlWhere = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // --- Count Total Records ---
        $countSql = "SELECT COUNT(*) FROM `{$this->tableName}` {$sqlWhere}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindings); // Filter bindings apply to count
        $totalRecords = (int)$countStmt->fetchColumn();

        // --- Sorting ('order' parameter) ---
        $orderByParts = [];
        if (!empty($requestParams['order'])) {
            $sorts = explode(';', $requestParams['order']);
            foreach ($sorts as $sort) {
                $parts = explode(',', $sort);
                $field = $parts[0];
                $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                if (in_array($field, $sortableFields) && in_array($direction, ['ASC', 'DESC'])) {
                    $orderByParts[] = "`{$field}` {$direction}";
                }
            }
        }
        $orderByClause = !empty($orderByParts) ? "ORDER BY " . implode(", ", $orderByParts) : "ORDER BY `{$allowedFields[0]}` ASC"; // Default order

        // --- Main Data Query ---
        $selectFields = !empty($allowedFields) ? implode(', ', array_map(fn($f) => "`{$f}`", $allowedFields)) : '*';
        $dataSql = "SELECT {$selectFields} FROM `{$this->tableName}` {$sqlWhere} {$orderByClause} LIMIT ? OFFSET ?";
        $dataStmt = $this->db->prepare($dataSql);

        $currentBindings = $bindings; // Start with filter bindings
        $currentBindings[] = $limit;  // PDO needs integer type for LIMIT/OFFSET
        $currentBindings[] = $offset;

        // Bind parameters with correct types for PDO
        for ($i = 0; $i < count($currentBindings); $i++) {
            if ($i >= count($bindings)) { // limit and offset
                 $dataStmt->bindValue($i + 1, (int)$currentBindings[$i], PDO::PARAM_INT);
            } else {
                 $dataStmt->bindValue($i + 1, $currentBindings[$i]);
            }
        }
        $dataStmt->execute();
        $results = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Prepare Response ---
        $response = [
            'pagination' => [
                'total_records' => $totalRecords,
                'limit' => $limit,
                'offset' => $offset,
                'current_page' => ($limit > 0) ? (floor($offset / $limit) + 1) : 1,
                'total_pages' => ($limit > 0 && $totalRecords > 0) ? ceil($totalRecords / $limit) : 1,
            ],
            'data' => $results
        ];
        if (($offset + $limit) < $totalRecords) {
            $response['pagination']['next_offset'] = $offset + $limit;
        }
        if ($offset > 0) {
            $response['pagination']['prev_offset'] = max(0, $offset - $limit);
        }
        Response::json($response);
    }
}
?>
```

**3.6. Example URL**

Here is an example URL demonstrating the use of these parameters for a hypothetical `/api/v1/products` endpoint:

`GET /api/v1/products?limit=20&offset=40&order=price,desc;name,asc&filters=category_id:eq:5,status:eq:available,rating:gte:4&q=durable`

This URL translates to the following request:
*   **Endpoint:** `/api/v1/products`
*   **Pagination:**
    *   `limit=20`: Return a maximum of 20 products.
    *   `offset=40`: Skip the first 40 products (effectively requesting page 3, if page 1 is offset 0).
*   **Sorting:**
    *   `order=price,desc;name,asc`: Order results primarily by `price` in descending order. For products with the same price, secondarily sort them by `name` in ascending order.
*   **Filtering:**
    *   `filters=category_id:eq:5`: Product's `category_id` must be equal to 5.
    *   `,status:eq:available`: AND product's `status` must be equal to 'available'.
    *   `,rating:gte:4`: AND product's `rating` must be greater than or equal to 4.
*   **Global Search:**
    *   `q=durable`: AND the predefined searchable fields for products (e.g., `name`, `description`) should contain the term "durable".

This comprehensive approach to `GET` operations provides clients with powerful and flexible tools to retrieve precisely the data they need, in the format they require, while maintaining security and performance considerations on the server side.
