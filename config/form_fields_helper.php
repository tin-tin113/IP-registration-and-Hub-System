<?php
/**
 * Form Fields Helper Functions
 * Provides utilities for working with dynamic form fields
 */

/**
 * Initialize form_fields table if it doesn't exist
 */
function initFormFieldsTable($conn) {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'form_fields'");
    if ($result->num_rows === 0) {
        // Read and execute the SQL file
        $sql_file = __DIR__ . '/../database/form_fields.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $conn->multi_query($sql);
            while ($conn->next_result()) {;} // Flush multi_query results
        }
    }
}

/**
 * Get all active form fields ordered by section and field_order
 * @param mysqli $conn Database connection
 * @param string|null $section Optional section filter
 * @return array Array of form field configurations
 */
function getActiveFormFields($conn, $section = null) {
    initFormFieldsTable($conn);
    
    $sql = "SELECT * FROM form_fields WHERE is_active = TRUE";
    if ($section) {
        $sql .= " AND field_section = '" . $conn->real_escape_string($section) . "'";
    }
    $sql .= " ORDER BY field_order ASC";
    
    $result = $conn->query($sql);
    $fields = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Parse JSON options
            if (!empty($row['field_options'])) {
                $row['field_options'] = json_decode($row['field_options'], true);
            }
            $fields[] = $row;
        }
    }
    return $fields;
}

/**
 * Get all form fields (including inactive) for admin management
 * @param mysqli $conn Database connection
 * @return array Array of all form field configurations
 */
function getAllFormFields($conn) {
    initFormFieldsTable($conn);
    
    $result = $conn->query("SELECT * FROM form_fields ORDER BY field_section, field_order ASC");
    $fields = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['field_options'])) {
                $row['field_options'] = json_decode($row['field_options'], true);
            }
            $fields[] = $row;
        }
    }
    return $fields;
}

/**
 * Get form field value from user profile
 * @param string $fieldName The field name
 * @param array $profile User profile data
 * @return string The field value or empty string
 */
function getFormFieldValue($fieldName, $profile) {
    // First check if it's a built-in column
    $builtinMapping = [
        'first_name' => 'first_name',
        'middle_name' => 'middle_name', 
        'last_name' => 'last_name',
        'suffix' => 'suffix',
        'email' => 'email',
        'contact_number' => 'contact_number',
        'birthdate' => 'birthdate',
        'gender' => 'gender',
        'nationality' => 'nationality',
        'employment_status' => 'employment_status',
        'employee_id' => 'employee_id',
        'college' => 'college',
        'address_street' => 'address_street',
        'address_barangay' => 'address_barangay',
        'address_province' => 'address_province',
        'address_city' => 'address_city',
        'address_postal' => 'address_postal'
    ];
    
    if (isset($builtinMapping[$fieldName]) && isset($profile[$builtinMapping[$fieldName]])) {
        return $profile[$builtinMapping[$fieldName]];
    }
    
    // Check custom_fields JSON
    if (!empty($profile['custom_fields'])) {
        $customFields = is_array($profile['custom_fields']) 
            ? $profile['custom_fields'] 
            : json_decode($profile['custom_fields'], true);
        if (isset($customFields[$fieldName])) {
            return $customFields[$fieldName];
        }
    }
    
    return '';
}

/**
 * Render a form field HTML based on its configuration
 * @param array $field Field configuration
 * @param string $value Current value
 * @param array $extraAttrs Extra HTML attributes
 * @return string HTML for the form field
 */
function renderFormField($field, $value = '', $extraAttrs = []) {
    $name = 'profile_' . htmlspecialchars($field['field_name']);
    $id = $name;
    $required = $field['is_required'] ? 'required' : '';
    $placeholder = htmlspecialchars($field['placeholder'] ?? '');
    $pattern = $field['validation_pattern'] ? 'pattern="' . htmlspecialchars($field['validation_pattern']) . '"' : '';
    
    $html = '';
    
    switch ($field['field_type']) {
        case 'select':
            $html = '<select name="' . $name . '" id="' . $id . '" ' . $required . '>';
            if (is_array($field['field_options'])) {
                foreach ($field['field_options'] as $option) {
                    $selected = ($value == $option) ? 'selected' : '';
                    $displayText = empty($option) ? 'Select ' . $field['field_label'] : $option;
                    $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($displayText) . '</option>';
                }
            }
            $html .= '</select>';
            break;
            
        case 'radio':
            $html = '<div style="display: flex; gap: 20px; align-items: center; padding: 12px 0;">';
            if (is_array($field['field_options'])) {
                foreach ($field['field_options'] as $option) {
                    $checked = ($value == $option) ? 'checked' : '';
                    $html .= '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">';
                    $html .= '<input type="radio" name="' . $name . '" value="' . htmlspecialchars($option) . '" ' . $checked . ' ' . $required . ' style="width: 18px; height: 18px; cursor: pointer;">';
                    $html .= '<span>' . htmlspecialchars($option) . '</span>';
                    $html .= '</label>';
                }
            }
            $html .= '</div>';
            break;
            
        case 'textarea':
            $html = '<textarea name="' . $name . '" id="' . $id . '" placeholder="' . $placeholder . '" ' . $required . '>' . htmlspecialchars($value) . '</textarea>';
            break;
            
        case 'date':
            $maxDate = '';
            if ($field['field_name'] === 'birthdate') {
                $maxDate = 'max="' . date('Y-m-d', strtotime('-16 years')) . '"';
            }
            $html = '<input type="date" name="' . $name . '" id="' . $id . '" value="' . htmlspecialchars($value) . '" ' . $required . ' ' . $maxDate . '>';
            break;
            
        default: // text, email, tel, number
            $type = in_array($field['field_type'], ['email', 'tel', 'number']) ? $field['field_type'] : 'text';
            $html = '<input type="' . $type . '" name="' . $name . '" id="' . $id . '" value="' . htmlspecialchars($value) . '" placeholder="' . $placeholder . '" ' . $pattern . ' ' . $required . '>';
            break;
    }
    
    return $html;
}

/**
 * Get fields grouped by section
 * @param mysqli $conn Database connection
 * @return array Fields grouped by section
 */
function getFieldsBySection($conn) {
    $fields = getActiveFormFields($conn);
    $grouped = [
        'name' => [],
        'contact' => [],
        'personal' => [],
        'employment' => [],
        'address' => []
    ];
    
    foreach ($fields as $field) {
        $section = $field['field_section'] ?? 'personal';
        if (isset($grouped[$section])) {
            $grouped[$section][] = $field;
        }
    }
    
    return $grouped;
}

/**
 * Save form field values to user profile
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param array $postData POST data from form
 * @return bool Success status
 */
function saveFormFieldValues($conn, $userId, $postData) {
    $fields = getActiveFormFields($conn);
    
    // Built-in fields that map to columns
    $builtinFields = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'email',
        'contact_number', 'birthdate', 'gender', 'nationality',
        'employment_status', 'employee_id', 'college',
        'address_street', 'address_barangay', 'address_province', 
        'address_city', 'address_postal'
    ];
    
    $builtinValues = [];
    $customValues = [];
    
    foreach ($fields as $field) {
        $postKey = 'profile_' . $field['field_name'];
        $value = trim($postData[$postKey] ?? '');
        
        if (in_array($field['field_name'], $builtinFields)) {
            $builtinValues[$field['field_name']] = $value;
        } else {
            $customValues[$field['field_name']] = $value;
        }
    }
    
    // Check if profile exists
    $check = $conn->prepare("SELECT id, custom_fields FROM user_profiles WHERE user_id = ?");
    $check->bind_param("i", $userId);
    $check->execute();
    $result = $check->get_result();
    $profileExists = $result->num_rows > 0;
    $existingCustom = [];
    if ($profileExists) {
        $row = $result->fetch_assoc();
        if (!empty($row['custom_fields'])) {
            $existingCustom = json_decode($row['custom_fields'], true) ?: [];
        }
    }
    $check->close();
    
    // Merge custom fields
    $finalCustom = array_merge($existingCustom, $customValues);
    $customJson = !empty($finalCustom) ? json_encode($finalCustom) : null;
    
    // Check if profile is complete
    $isComplete = !empty($builtinValues['first_name']) && !empty($builtinValues['last_name']) && 
                  !empty($builtinValues['email']) && !empty($builtinValues['birthdate']) && 
                  !empty($builtinValues['employee_id']) && !empty($builtinValues['college']) && 
                  !empty($builtinValues['contact_number']);
    
    if ($profileExists) {
        $sql = "UPDATE user_profiles SET 
            first_name=?, middle_name=?, last_name=?, suffix=?, email=?, birthdate=?, 
            gender=?, nationality=?, employment_status=?, employee_id=?, college=?, 
            contact_number=?, address_street=?, address_barangay=?, address_city=?, 
            address_province=?, address_postal=?, custom_fields=?, is_complete=?, updated_at=NOW() 
            WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssssssssii", 
            $builtinValues['first_name'], $builtinValues['middle_name'], $builtinValues['last_name'], 
            $builtinValues['suffix'], $builtinValues['email'], $builtinValues['birthdate'],
            $builtinValues['gender'], $builtinValues['nationality'], $builtinValues['employment_status'],
            $builtinValues['employee_id'], $builtinValues['college'], $builtinValues['contact_number'],
            $builtinValues['address_street'], $builtinValues['address_barangay'], $builtinValues['address_city'],
            $builtinValues['address_province'], $builtinValues['address_postal'], $customJson, $isComplete, $userId);
    } else {
        $sql = "INSERT INTO user_profiles 
            (user_id, first_name, middle_name, last_name, suffix, email, birthdate, 
             gender, nationality, employment_status, employee_id, college, contact_number, 
             address_street, address_barangay, address_city, address_province, address_postal, 
             custom_fields, is_complete) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssssssssssssi", 
            $userId, $builtinValues['first_name'], $builtinValues['middle_name'], $builtinValues['last_name'],
            $builtinValues['suffix'], $builtinValues['email'], $builtinValues['birthdate'],
            $builtinValues['gender'], $builtinValues['nationality'], $builtinValues['employment_status'],
            $builtinValues['employee_id'], $builtinValues['college'], $builtinValues['contact_number'],
            $builtinValues['address_street'], $builtinValues['address_barangay'], $builtinValues['address_city'],
            $builtinValues['address_province'], $builtinValues['address_postal'], $customJson, $isComplete);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}
