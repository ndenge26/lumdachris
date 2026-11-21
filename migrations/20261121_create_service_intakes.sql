CREATE TABLE IF NOT EXISTS service_intakes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_type ENUM('due_diligence', 'property_dispute') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    property_location VARCHAR(255) NOT NULL,
    issue_type ENUM('boundary_dispute', 'certificate_verification', 'tenancy_issue') NOT NULL,
    request_details TEXT NULL,
    document_original_name VARCHAR(180) NULL,
    document_storage_name VARCHAR(80) NULL,
    document_mime ENUM('application/pdf', 'image/jpeg') NULL,
    document_size INT UNSIGNED NULL,
    status ENUM('received', 'in_review', 'resolved') NOT NULL DEFAULT 'received',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_intakes_issue_status (issue_type, status),
    KEY idx_service_intakes_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
