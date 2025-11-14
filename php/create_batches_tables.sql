-- Create batches table
-- Each order generates a unique batch number
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique batch ID',
    batch_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique batch number (e.g., BATCH-001)',
    order_id INT UNSIGNED NOT NULL COMMENT 'Reference to orders.id',
    supplier_id INT NOT NULL COMMENT 'Reference to suppliers.id',
    created_date DATE NOT NULL COMMENT 'Date when batch was created',
    status ENUM('active', 'expired', 'completed') DEFAULT 'active' COMMENT 'Batch status',
    notes TEXT COMMENT 'Additional notes about the batch',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_batch_number (batch_number),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_created_date (created_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create batch_items table
-- Links batches to medicines with their quantities and expiration dates
CREATE TABLE IF NOT EXISTS batch_items (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique batch item ID',
    batch_id INT NOT NULL COMMENT 'Reference to batches.id',
    medicine_id INT UNSIGNED NOT NULL COMMENT 'Reference to medicines.id',
    quantity INT NOT NULL DEFAULT 0 COMMENT 'Quantity of medicine in this batch',
    expiration_date DATE COMMENT 'Expiration date for this specific item',
    received_quantity INT DEFAULT 0 COMMENT 'Actual quantity received (may differ from ordered)',
    is_expired TINYINT(1) DEFAULT 0 COMMENT 'Flag to track if this item has expired',
    expired_at TIMESTAMP NULL COMMENT 'Timestamp when item expired',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    INDEX idx_batch_id (batch_id),
    INDEX idx_medicine_id (medicine_id),
    INDEX idx_expiration_date (expiration_date),
    INDEX idx_is_expired (is_expired)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

