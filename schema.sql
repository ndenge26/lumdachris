CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'agent', 'landlord', 'legal_expert') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    listing_type ENUM('buy_land', 'sell_land', 'lease', 'tenancy') NOT NULL,
    property_type ENUM('land', 'house', 'apartment') NOT NULL DEFAULT 'land',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    city VARCHAR(100) NOT NULL COMMENT 'Examples include Yaounde and Douala',
    location_details VARCHAR(255) NULL,
    price_xaf BIGINT UNSIGNED NOT NULL,
    dimensions_sqm DECIMAL(12, 2) UNSIGNED NOT NULL,
    bedrooms SMALLINT UNSIGNED NULL,
    has_land_certificate BOOLEAN NOT NULL DEFAULT FALSE,
    certificate_status ENUM('not_submitted', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'not_submitted',
    certificate_original_name VARCHAR(180) NULL,
    certificate_storage_name VARCHAR(80) NULL,
    certificate_mime ENUM('application/pdf', 'image/jpeg') NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listings_owner (owner_user_id),
    KEY idx_listings_city_type (city, listing_type),
    KEY idx_listings_active (is_active),
    CONSTRAINT fk_listings_owner FOREIGN KEY (owner_user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id BIGINT UNSIGNED NOT NULL,
    storage_name VARCHAR(80) NOT NULL,
    alt_text VARCHAR(200) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listing_images_listing (listing_id),
    CONSTRAINT fk_listing_images_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NULL,
    service_type ENUM('due_diligence', 'property_dispute_consultation') NOT NULL,
    status ENUM('requested', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'requested',
    description TEXT NULL,
    assigned_legal_expert_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_services_requester (requester_user_id),
    KEY idx_services_listing (listing_id),
    KEY idx_services_type_status (service_type, status),
    KEY idx_services_legal_expert (assigned_legal_expert_id),
    CONSTRAINT fk_services_requester FOREIGN KEY (requester_user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_services_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_services_legal_expert FOREIGN KEY (assigned_legal_expert_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chatbot_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    intent ENUM('buy', 'sell') NOT NULL,
    budget_location VARCHAR(300) NOT NULL,
    language ENUM('en', 'fr') NOT NULL DEFAULT 'en',
    status ENUM('received', 'contacted', 'closed') NOT NULL DEFAULT 'received',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chatbot_leads_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_intakes (
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
    document_review_status ENUM('not_reviewed', 'verified', 'rejected') NOT NULL DEFAULT 'not_reviewed',
    status ENUM('received', 'in_review', 'resolved') NOT NULL DEFAULT 'received',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_intakes_issue_status (issue_type, status),
    KEY idx_service_intakes_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;