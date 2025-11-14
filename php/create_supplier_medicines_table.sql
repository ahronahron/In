-- Create supplier_medicines junction table in Inventory_system_db database
-- This table links suppliers to medicines (many-to-many relationship)

CREATE TABLE IF NOT EXISTS supplier_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL COMMENT 'Reference to suppliers.id',
    medicine_id INT UNSIGNED NOT NULL COMMENT 'Reference to medicines.id',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_medicine (supplier_id, medicine_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_medicine (medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Supplier-Medicines junction table';

