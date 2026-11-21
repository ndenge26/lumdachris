ALTER TABLE listings
    ADD COLUMN property_type ENUM('land', 'house', 'apartment') NOT NULL DEFAULT 'land' AFTER listing_type,
    ADD COLUMN bedrooms SMALLINT UNSIGNED NULL AFTER dimensions_sqm,
    ADD COLUMN certificate_status ENUM('not_submitted', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'not_submitted' AFTER has_land_certificate,
    ADD COLUMN certificate_original_name VARCHAR(180) NULL AFTER certificate_status,
    ADD COLUMN certificate_storage_name VARCHAR(80) NULL AFTER certificate_original_name,
    ADD COLUMN certificate_mime ENUM('application/pdf', 'image/jpeg') NULL AFTER certificate_storage_name;

UPDATE listings SET certificate_status = 'verified' WHERE has_land_certificate = 1;

ALTER TABLE service_intakes
    ADD COLUMN document_review_status ENUM('not_reviewed', 'verified', 'rejected') NOT NULL DEFAULT 'not_reviewed' AFTER document_size;

CREATE TABLE IF NOT EXISTS listing_images (
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

CREATE TABLE IF NOT EXISTS chatbot_leads (
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
