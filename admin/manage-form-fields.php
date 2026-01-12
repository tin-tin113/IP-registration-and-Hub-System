<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/form_fields_helper.php';

requireRole(['clerk', 'director']);

$error = '';
$success = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $field_label = trim($_POST['field_label'] ?? '');
            $field_name = trim($_POST['field_name'] ?? '');
            // Auto-generate field_name from label if not provided
            if (empty($field_name) && !empty($field_label)) {
                $field_name = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $field_label)));
            }
            $field_name = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $field_name)));
            $field_type = $_POST['field_type'] ?? 'text';
            $field_section = $_POST['field_section'] ?? 'personal';
            $placeholder = trim($_POST['placeholder'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $field_options = null;
            
            if (in_array($field_type, ['select', 'radio']) && !empty($_POST['field_options'])) {
                $options = array_filter(array_map('trim', explode("\n", $_POST['field_options'])));
                $field_options = json_encode(array_values($options));
            }
            
            if (empty($field_name) || empty($field_label)) {
                $error = 'Field name and label are required.';
            } else {
                // Get max order for section
                $maxOrder = $conn->query("SELECT MAX(field_order) as max_order FROM form_fields WHERE field_section = '$field_section'")->fetch_assoc()['max_order'] ?? 0;
                
                $newOrder = $maxOrder + 1;
                $stmt = $conn->prepare("INSERT INTO form_fields (field_name, field_label, field_type, field_options, placeholder, is_required, is_active, is_builtin, field_section, field_order) VALUES (?, ?, ?, ?, ?, ?, TRUE, FALSE, ?, $newOrder)");
                $stmt->bind_param("sssssis", $field_name, $field_label, $field_type, $field_options, $placeholder, $is_required, $field_section);
                // Note: field_order is set via $newOrder in the SQL directly
                
                if ($stmt->execute()) {
                    $success = 'Field added successfully.';
                    auditLog('Add Form Field', 'FormField', $stmt->insert_id, null, json_encode(['name' => $field_name]));
                } else {
                    $error = 'Failed to add field. Name may already exist.';
                }
                $stmt->close();
            }
            break;
            
        case 'edit':
            $field_id = (int)($_POST['field_id'] ?? 0);
            $field_label = trim($_POST['field_label'] ?? '');
            $field_section = $_POST['field_section'] ?? 'personal';
            $placeholder = trim($_POST['placeholder'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $field_options = null;
            
            // Get current field_type from database (since we don't allow changing it in edit)
            $field_type = 'text';
            $type_check = $conn->query("SELECT field_type, field_options FROM form_fields WHERE id = $field_id");
            if ($type_row = $type_check->fetch_assoc()) {
                $field_type = $type_row['field_type'];
            }
            
            // Handle options for select/radio fields
            if (in_array($field_type, ['select', 'radio'])) {
                if (!empty($_POST['field_options'])) {
                    $options = array_filter(array_map('trim', explode("\n", $_POST['field_options'])));
                    $field_options = json_encode(array_values($options));
                }
            }
            
            if ($field_id > 0 && !empty($field_label)) {
                $stmt = $conn->prepare("UPDATE form_fields SET field_label=?, field_type=?, field_options=?, placeholder=?, is_required=?, field_section=? WHERE id=?");
                $stmt->bind_param("ssssisi", $field_label, $field_type, $field_options, $placeholder, $is_required, $field_section, $field_id);
                
                if ($stmt->execute()) {
                    $success = 'Field updated successfully.';
                    auditLog('Edit Form Field', 'FormField', $field_id);
                } else {
                    $error = 'Failed to update field.';
                }
                $stmt->close();
            }
            break;
            
        case 'toggle':
            $field_id = (int)($_POST['field_id'] ?? 0);
            if ($field_id > 0) {
                $conn->query("UPDATE form_fields SET is_active = NOT is_active WHERE id = $field_id");
                $success = 'Field status updated.';
                auditLog('Toggle Form Field', 'FormField', $field_id);
            }
            break;
            
        case 'delete':
            $field_id = (int)($_POST['field_id'] ?? 0);
            if ($field_id > 0) {
                // Only allow deleting non-builtin fields
                $check = $conn->query("SELECT is_builtin FROM form_fields WHERE id = $field_id")->fetch_assoc();
                if ($check && !$check['is_builtin']) {
                    $conn->query("DELETE FROM form_fields WHERE id = $field_id AND is_builtin = FALSE");
                    $success = 'Field deleted successfully.';
                    auditLog('Delete Form Field', 'FormField', $field_id);
                } else {
                    $error = 'Built-in fields cannot be deleted.';
                }
            }
            break;
            
        case 'reorder':
            $orders = json_decode($_POST['orders'] ?? '[]', true);
            if (is_array($orders)) {
                foreach ($orders as $item) {
                    $id = (int)$item['id'];
                    $order = (int)$item['order'];
                    $conn->query("UPDATE form_fields SET field_order = $order WHERE id = $id");
                }
                $success = 'Field order updated.';
            }
            break;
            
        case 'import':
            $import_data = json_decode($_POST['import_data'] ?? '[]', true);
            $imported = 0;
            if (is_array($import_data)) {
                foreach ($import_data as $field) {
                    $fn = preg_replace('/[^a-z0-9_]/', '', strtolower($field['field_name'] ?? ''));
                    $fl = trim($field['field_label'] ?? '');
                    $ft = $field['field_type'] ?? 'text';
                    $fo = isset($field['field_options']) ? json_encode($field['field_options']) : null;
                    $fp = $field['placeholder'] ?? '';
                    $fr = $field['is_required'] ? 1 : 0;
                    $fs = $field['field_section'] ?? 'personal';
                    
                    if (!empty($fn) && !empty($fl)) {
                        $check = $conn->query("SELECT id FROM form_fields WHERE field_name = '" . $conn->real_escape_string($fn) . "'");
                        if ($check->num_rows === 0) {
                            $maxOrder = $conn->query("SELECT MAX(field_order) as m FROM form_fields")->fetch_assoc()['m'] ?? 0;
                            $stmt = $conn->prepare("INSERT INTO form_fields (field_name, field_label, field_type, field_options, placeholder, is_required, is_active, is_builtin, field_section, field_order) VALUES (?, ?, ?, ?, ?, ?, TRUE, FALSE, ?, ?)");
                            $newOrder = $maxOrder + 1;
                            $stmt->bind_param("sssssis", $fn, $fl, $ft, $fo, $fp, $fr, $fs);
                            $conn->query("SET @new_order = $newOrder");
                            if ($stmt->execute()) $imported++;
                            $stmt->close();
                        }
                    }
                }
                $success = "Imported $imported fields successfully.";
                auditLog('Import Form Fields', 'FormField', null, null, "Imported $imported fields");
            }
            break;
            
        case 'update_instructions':
            $instructions_file = __DIR__ . '/../config/form_instructions.json';
            $section_key = $_POST['section_key'] ?? '';
            $section_title = trim($_POST['section_title'] ?? '');
            $section_items = $_POST['section_items'] ?? '';
            
            // Parse items (one per line)
            $items = array_filter(array_map('trim', explode("\n", $section_items)));
            
            // Load existing instructions
            $instructions = [];
            if (file_exists($instructions_file)) {
                $instructions = json_decode(file_get_contents($instructions_file), true) ?? [];
            }
            
            // Update the section
            if (!empty($section_key)) {
                $instructions[$section_key] = [
                    'title' => $section_title,
                    'items' => array_values($items)
                ];
                
                // Save back to file
                if (file_put_contents($instructions_file, json_encode($instructions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                    $success = 'Instructions updated successfully.';
                    auditLog('Update Form Instructions', 'FormInstructions', null, null, $section_key);
                } else {
                    $error = 'Failed to save instructions.';
                }
            }
            break;
    }
}

$fields = getAllFormFields($conn);
$sections = ['name' => 'Name Fields', 'contact' => 'Contact Info', 'personal' => 'Personal Details', 'employment' => 'Employment/Academic', 'address' => 'Address Information'];

// Load form instructions from JSON file
$instructions_file = __DIR__ . '/../config/form_instructions.json';
$form_instructions = [];
if (file_exists($instructions_file)) {
    $form_instructions = json_decode(file_get_contents($instructions_file), true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Form Builder - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #F8FAFC; color: #1E293B; }
    
    .container { padding: 32px; max-width: 1400px; margin: 0 auto; }
    
    .page-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white; padding: 32px; border-radius: 16px; margin-bottom: 32px;
      box-shadow: 0 4px 16px rgba(10, 77, 46, 0.2);
    }
    .page-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
    .page-header p { opacity: 0.9; font-size: 14px; }
    
    .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
    .alert-danger { background: #FEE2E2; color: #7F1D1D; border-left: 4px solid #EF4444; }
    
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    
    .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3); }
    .btn-secondary { background: #E2E8F0; color: #475569; }
    .btn-danger { background: #EF4444; color: white; }
    .btn-small { padding: 6px 12px; font-size: 12px; }
    
    .section-card { background: white; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .section-title { font-size: 16px; font-weight: 700; color: #0A4D2E; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
    .section-title i { color: #DAA520; }
    
    .field-list { display: flex; flex-direction: column; gap: 10px; }
    
    .field-item {
      background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px;
      display: flex; justify-content: space-between; align-items: center; gap: 16px;
      transition: all 0.2s; cursor: grab;
    }
    .field-item:hover { border-color: #1B7F4D; background: white; }
    .field-item.inactive { opacity: 0.5; background: #FEF3C7; }
    .field-item.builtin { border-left: 4px solid #DAA520; }
    
    .field-info { flex: 1; }
    .field-name { font-weight: 600; color: #1E293B; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .field-meta { font-size: 12px; color: #64748B; margin-top: 4px; }
    .field-meta span { margin-right: 12px; }
    
    .badge { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
    .badge-required { background: #FECACA; color: #7F1D1D; }
    .badge-builtin { background: #FEF3C7; color: #92400E; }
    .badge-inactive { background: #E2E8F0; color: #475569; }
    
    .field-actions { display: flex; gap: 8px; }
    
    /* Modal */
    .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .modal.active { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { font-size: 18px; font-weight: 700; color: #1E293B; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748B; }
    .modal-body { padding: 24px; }
    
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1E293B; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      outline: none; border-color: #1B7F4D; box-shadow: 0 0 0 3px rgba(27, 127, 77, 0.1);
    }
    .form-group small { color: #64748B; font-size: 12px; }
    
    .checkbox-group { display: flex; align-items: center; gap: 8px; }
    .checkbox-group input { width: auto; }
    
    @media (max-width: 768px) {
      .container { padding: 16px; }
      .field-item { flex-direction: column; align-items: flex-start; }
      .field-actions { width: 100%; justify-content: flex-end; }
    }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="page-header">
      <h1><i class="fas fa-puzzle-piece" style="margin-right: 12px;"></i>Form Builder</h1>
      <p>Customize the personal information form fields for IP applications</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="toolbar">
      <div>
        <span style="color: #64748B; font-size: 14px;">
          <i class="fas fa-info-circle"></i> Drag fields to reorder. Built-in fields (gold border) can be hidden but not deleted.
        </span>
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-secondary" onclick="previewForm()">
          <i class="fas fa-eye"></i> Preview
        </button>
        <button class="btn btn-primary" onclick="openModal('addModal')">
          <i class="fas fa-plus"></i> Add Custom Field
        </button>
      </div>
    </div>
    
    <?php foreach ($sections as $sectionKey => $sectionTitle): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-grip-vertical"></i>
          <?php echo $sectionTitle; ?>
        </div>
        <div class="field-list" data-section="<?php echo $sectionKey; ?>">
          <?php 
          $sectionFields = array_filter($fields, fn($f) => $f['field_section'] === $sectionKey);
          foreach ($sectionFields as $field): 
          ?>
            <div class="field-item <?php echo $field['is_builtin'] ? 'builtin' : ''; ?> <?php echo !$field['is_active'] ? 'inactive' : ''; ?>" data-id="<?php echo $field['id']; ?>">
              <div class="field-info">
                <div class="field-name">
                  <?php echo htmlspecialchars($field['field_label']); ?>
                  <?php if ($field['is_required']): ?><span class="badge badge-required">Required</span><?php endif; ?>
                  <?php if ($field['is_builtin']): ?><span class="badge badge-builtin">Built-in</span><?php endif; ?>
                  <?php if (!$field['is_active']): ?><span class="badge badge-inactive">Hidden</span><?php endif; ?>
                </div>
                <div class="field-meta">
                  <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($field['field_name']); ?></span>
                  <span><i class="fas fa-tag"></i> <?php echo ucfirst($field['field_type']); ?></span>
                </div>
              </div>
              <div class="field-actions">
                <button class="btn btn-secondary btn-small" onclick="editField(<?php echo htmlspecialchars(json_encode($field)); ?>)">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                  <button type="submit" class="btn btn-secondary btn-small" title="<?php echo $field['is_active'] ? 'Hide field' : 'Show field'; ?>">
                    <i class="fas fa-<?php echo $field['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                  </button>
                </form>
                <?php if (!$field['is_builtin']): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field? This cannot be undone.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-small"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    
    <!-- Requirements & Instructions Editor Section -->
    <div class="section-card" style="border-left: 4px solid #DAA520; margin-top: 32px;">
      <div class="section-title" style="color: #1B7F4D;">
        <i class="fas fa-clipboard-list"></i>
        System Instructions & Requirements
        <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
          (Application Form & Payment Page)
        </span>
      </div>
      <p style="font-size: 13px; color: #666; margin-bottom: 16px;">
        <i class="fas fa-info-circle"></i> Edit the instructions and requirements shown to applicants on the Application Form and Payment Upload Page.
      </p>
      
      <div class="field-list">
        <?php foreach ($form_instructions as $key => $section): ?>
          <div class="field-item" style="cursor: default;">
            <div class="field-info">
              <div class="field-name">
                <?php echo htmlspecialchars($section['title']); ?>
                <span class="badge badge-builtin"><?php echo count($section['items']); ?> items</span>
              </div>
              <div class="field-meta">
                <?php 
                  $preview = implode(' • ', array_slice(array_map(function($item) {
                    return strip_tags(substr($item, 0, 30)) . (strlen($item) > 30 ? '...' : '');
                  }, $section['items']), 0, 2)); 
                ?>
                <span><?php echo htmlspecialchars($preview); ?></span>
              </div>
            </div>
            <div class="field-actions">
              <button class="btn btn-secondary btn-small" onclick="editInstructions('<?php echo $key; ?>', <?php echo htmlspecialchars(json_encode($section)); ?>)">
                <i class="fas fa-edit"></i> Edit
              </button>
            </div>
          </div>
        <?php endforeach; ?>
        
        <?php if (empty($form_instructions)): ?>
          <p style="color: #999; font-style: italic; text-align: center; padding: 20px;">
            No instructions configured. The default instructions will be shown.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- Add Field Modal -->
  <div class="modal" id="addModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-plus-circle" style="color: #1B7F4D;"></i> Add Custom Field</h3>
        <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          
          <div class="form-group">
            <label>Field Label *</label>
            <input type="text" name="field_label" required placeholder="e.g., Emergency Contact Name">
          </div>
          
          <div class="form-group">
            <label>Field Name (Internal)</label>
            <input type="text" name="field_name" placeholder="e.g., emergency_contact_name" pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only">
          </div>
          
          <div class="form-group">
            <label>Field Type</label>
            <select name="field_type" onchange="toggleOptionsField(this, 'addOptions')">
              <option value="text">Text Input</option>
              <option value="email">Email</option>
              <option value="tel">Phone Number</option>
              <option value="number">Number</option>
              <option value="date">Date</option>
              <option value="select">Dropdown Select</option>
              <option value="radio">Radio Buttons</option>
              <option value="textarea">Text Area</option>
            </select>
          </div>
          
          <div class="form-group" id="addOptions" style="display: none;">
            <label>Options (one per line)</label>
            <textarea name="field_options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
          </div>
          
          <div class="form-group">
            <label>Section</label>
            <select name="field_section">
              <?php foreach ($sections as $key => $title): ?>
                <option value="<?php echo $key; ?>"><?php echo $title; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label>Placeholder</label>
            <input type="text" name="placeholder" placeholder="e.g., Enter contact name">
          </div>
          
          <div class="form-group checkbox-group">
            <input type="checkbox" name="is_required" id="add_required">
            <label for="add_required" style="margin: 0;">Required field</label>
          </div>
          
          <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Add Field</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Edit Field Modal -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-edit" style="color: #1B7F4D;"></i> Edit Field</h3>
        <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form method="POST" id="editForm">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="field_id" id="edit_field_id">
          
          <div class="form-group">
            <label>Field Label *</label>
            <input type="text" name="field_label" id="edit_field_label" required>
          </div>
          
          <div class="form-group">
            <label>Section</label>
            <select name="field_section" id="edit_field_section">
              <?php foreach ($sections as $key => $title): ?>
                <option value="<?php echo $key; ?>"><?php echo $title; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label>Placeholder</label>
            <input type="text" name="placeholder" id="edit_placeholder">
          </div>
          
          <div class="form-group checkbox-group">
            <input type="checkbox" name="is_required" id="edit_is_required">
            <label for="edit_is_required" style="margin: 0;">Required field</label>
          </div>
          
          <div class="form-group" id="editOptionsGroup" style="display: none;">
            <label>Dropdown Options <small style="color: #666; font-weight: normal;">(one per line)</small></label>
            <textarea name="field_options" id="edit_field_options" rows="6" placeholder="Enter each option on a new line"></textarea>
            <small style="color: #888;">Each line becomes a dropdown choice. Changes apply to new submissions.</small>
          </div>
          
          <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Instructions Edit Modal -->
  <div class="modal" id="instructionsModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-clipboard-list" style="color: #DAA520;"></i> Edit Instructions</h3>
        <button class="modal-close" onclick="closeModal('instructionsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form method="POST" id="instructionsForm">
          <input type="hidden" name="action" value="update_instructions">
          <input type="hidden" name="section_key" id="inst_section_key">
          
          <div class="form-group">
            <label>Section Title *</label>
            <input type="text" name="section_title" id="inst_section_title" required placeholder="e.g., What to Include:">
          </div>
          
          <div class="form-group">
            <label>Instruction Items <small style="color: #666; font-weight: normal;">(one per line)</small></label>
            <textarea name="section_items" id="inst_section_items" rows="8" placeholder="Enter each instruction on a new line&#10;&#10;You can use basic HTML like &lt;strong&gt;bold&lt;/strong&gt;"></textarea>
            <small style="color: #888;">Each line becomes a bullet point. HTML tags like &lt;strong&gt; are allowed.</small>
          </div>
          
          <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Save Instructions</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('instructionsModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    function openModal(id) {
      document.getElementById(id).classList.add('active');
    }
    
    function closeModal(id) {
      document.getElementById(id).classList.remove('active');
    }
    
    function toggleOptionsField(select, targetId) {
      const target = document.getElementById(targetId);
      if (['select', 'radio'].includes(select.value)) {
        target.style.display = 'block';
      } else {
        target.style.display = 'none';
      }
    }
    
    function editField(field) {
      document.getElementById('edit_field_id').value = field.id;
      document.getElementById('edit_field_label').value = field.field_label;
      document.getElementById('edit_field_section').value = field.field_section;
      document.getElementById('edit_placeholder').value = field.placeholder || '';
      document.getElementById('edit_is_required').checked = field.is_required == 1;
      
      // Show options editor for select/radio fields
      const optionsGroup = document.getElementById('editOptionsGroup');
      const optionsTextarea = document.getElementById('edit_field_options');
      
      if (['select', 'radio'].includes(field.field_type)) {
        optionsGroup.style.display = 'block';
        if (field.field_options && Array.isArray(field.field_options)) {
          optionsTextarea.value = field.field_options.join('\n');
        } else {
          optionsTextarea.value = '';
        }
      } else {
        optionsGroup.style.display = 'none';
        optionsTextarea.value = '';
      }
      
      openModal('editModal');
    }
    
    function editInstructions(key, section) {
      document.getElementById('inst_section_key').value = key;
      document.getElementById('inst_section_title').value = section.title || '';
      document.getElementById('inst_section_items').value = (section.items || []).join('\n');
      openModal('instructionsModal');
    }
    
    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
      });
    });
    
    // ======== DRAG AND DROP REORDERING ========
    let draggedItem = null;
    
    document.querySelectorAll('.field-item').forEach(item => {
      item.setAttribute('draggable', 'true');
      
      item.addEventListener('dragstart', function(e) {
        draggedItem = this;
        this.style.opacity = '0.5';
        e.dataTransfer.effectAllowed = 'move';
      });
      
      item.addEventListener('dragend', function(e) {
        this.style.opacity = '1';
        document.querySelectorAll('.field-item').forEach(i => i.classList.remove('drag-over'));
      });
      
      item.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });
      
      item.addEventListener('dragenter', function(e) {
        this.classList.add('drag-over');
      });
      
      item.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
      });
      
      item.addEventListener('drop', function(e) {
        e.preventDefault();
        if (draggedItem !== this) {
          const allItems = [...this.parentNode.querySelectorAll('.field-item')];
          const draggedIndex = allItems.indexOf(draggedItem);
          const droppedIndex = allItems.indexOf(this);
          
          if (draggedIndex < droppedIndex) {
            this.parentNode.insertBefore(draggedItem, this.nextSibling);
          } else {
            this.parentNode.insertBefore(draggedItem, this);
          }
          
          saveFieldOrder(this.parentNode);
        }
        this.classList.remove('drag-over');
      });
    });
    
    function saveFieldOrder(container) {
      const items = container.querySelectorAll('.field-item');
      const orders = [];
      items.forEach((item, index) => {
        orders.push({ id: parseInt(item.dataset.id), order: index + 1 });
      });
      
      // Send reorder request
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `<input type="hidden" name="action" value="reorder">
                        <input type="hidden" name="orders" value='${JSON.stringify(orders)}'>`;
      document.body.appendChild(form);
      form.submit();
    }
    
    // ======== PREVIEW MODE ========
    function previewForm() {
      openModal('previewModal');
    }
    

  </script>
  
  <!-- Add drag-over style -->
  <style>
    .field-item.drag-over { border: 2px dashed #1B7F4D; background: #E8F5E9; }
  </style>
  
  <!-- Preview Modal -->
  <div class="modal" id="previewModal">
    <div class="modal-content" style="max-width: 700px;">
      <div class="modal-header">
        <h3><i class="fas fa-eye" style="color: #1B7F4D;"></i> Form Preview</h3>
        <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size: 13px; color: #666; margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
          This is how your form fields will appear to users on the Apply page.
        </p>
        <?php 
        $previewFields = getFieldsBySection($conn);
        $previewLabels = ['name' => 'Name Information', 'contact' => 'Contact', 'personal' => 'Personal', 'employment' => 'Employment', 'address' => 'Address'];
        foreach ($previewLabels as $key => $label): 
          $sectionFields = $previewFields[$key] ?? [];
          if (empty($sectionFields)) continue;
        ?>
        <div style="margin-bottom: 20px;">
          <h5 style="font-size: 13px; color: #666; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;"><?php echo $label; ?></h5>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
            <?php foreach ($sectionFields as $f): ?>
            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 12px;"><?php echo htmlspecialchars($f['field_label']); ?><?php if($f['is_required']): ?> <span style="color:red;">*</span><?php endif; ?></label>
              <?php echo renderFormField($f, ''); ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  
</body>
</html>
