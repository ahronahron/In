-- Create Archive tables for Inventory_system_db database
-- These tables store expired items, cancelled orders, and deleted items

-- Archive table for deleted medicines
CREATE TABLE IF NOT EXISTS archived_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
    original_id INT UNSIGNED NOT NULL COMMENT 'Original medicine ID before deletion',
    ndc VARCHAR(50) NULL COMMENT 'National Drug Code',
    name VARCHAR(255) NOT NULL COMMENT 'Medicine name',
    manufacturer VARCHAR(255) NULL COMMENT 'Manufacturer name',
    category VARCHAR(100) NULL COMMENT 'Medicine category',
    dosage_form VARCHAR(100) NULL COMMENT 'Dosage form',
    price DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Price per unit',
    quantity INT DEFAULT 0 COMMENT 'Quantity at time of deletion',
    description TEXT NULL COMMENT 'Medicine description',
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was deleted',
    deleted_by VARCHAR(255) NULL COMMENT 'User who deleted the item',
    reason TEXT NULL COMMENT 'Reason for deletion',
    INDEX idx_original_id (original_id),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived deleted medicines';

-- Archive table for cancelled orders
CREATE TABLE IF NOT EXISTS archived_orders (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
    original_id INT UNSIGNED NOT NULL COMMENT 'Original order ID',
    supplier_id INT NOT NULL COMMENT 'Reference to supplier',
    supplier_name VARCHAR(255) NULL COMMENT 'Supplier name at time of cancellation',
    order_date DATE NOT NULL COMMENT 'Date when order was placed',
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When order was cancelled',
    cancelled_by VARCHAR(255) NULL COMMENT 'User who cancelled the order',
    total_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total order amount',
    notes TEXT NULL COMMENT 'Additional notes about the order',
    cancellation_reason TEXT NULL COMMENT 'Reason for cancellation',
    original_status VARCHAR(50) NULL COMMENT 'Status before cancellation',
    INDEX idx_original_id (original_id),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_cancelled_at (cancelled_at),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived cancelled orders';

-- Archive table for cancelled order items
CREATE TABLE IF NOT EXISTS archived_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
    archived_order_id INT NOT NULL COMMENT 'Reference to archived_orders.id',
    original_item_id INT NULL COMMENT 'Original order_item ID',
    medicine_id INT UNSIGNED NULL COMMENT 'Reference to medicine (may be deleted)',
    medicine_name VARCHAR(255) NULL COMMENT 'Medicine name at time of cancellation',
    quantity INT NOT NULL DEFAULT 0 COMMENT 'Quantity of medicine ordered',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Price per unit',
    INDEX idx_archived_order_id (archived_order_id),
    INDEX idx_medicine_id (medicine_id),
    FOREIGN KEY (archived_order_id) REFERENCES archived_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived cancelled order items';

-- Archive table for expired batch items
CREATE TABLE IF NOT EXISTS archived_expired_items (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
    original_batch_item_id INT NULL COMMENT 'Original batch_item ID',
    batch_id INT NULL COMMENT 'Reference to batch',
    batch_number VARCHAR(50) NULL COMMENT 'Batch number',
    medicine_id INT UNSIGNED NULL COMMENT 'Reference to medicine (may be deleted)',
    medicine_name VARCHAR(255) NULL COMMENT 'Medicine name',
    medicine_ndc VARCHAR(50) NULL COMMENT 'Medicine NDC',
    quantity INT NOT NULL DEFAULT 0 COMMENT 'Quantity that expired',
    expiration_date DATE NOT NULL COMMENT 'Expiration date',
    expired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was marked as expired',
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was archived',
    supplier_id INT NULL COMMENT 'Supplier ID',
    supplier_name VARCHAR(255) NULL COMMENT 'Supplier name',
    INDEX idx_original_batch_item_id (original_batch_item_id),
    INDEX idx_batch_id (batch_id),
    INDEX idx_medicine_id (medicine_id),
    INDEX idx_expiration_date (expiration_date),
    INDEX idx_expired_at (expired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived expired batch items';

