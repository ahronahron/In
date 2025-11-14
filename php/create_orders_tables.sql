-- Create Orders and Order Items tables in Inventory_system_db database

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique order ID',
    supplier_id INT NOT NULL COMMENT 'Reference to supplier',
    order_date DATE NOT NULL COMMENT 'Date when order was placed',
    status ENUM('pending', 'shipping', 'completed', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Order status',
    total_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total order amount',
    notes TEXT NULL COMMENT 'Additional notes about the order',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    INDEX idx_supplier (supplier_id),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orders table';

-- Order Items table (user provided structure)
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique order item ID',
    order_id INT UNSIGNED NOT NULL COMMENT 'Reference to order',
    medicine_id INT UNSIGNED NOT NULL COMMENT 'Reference to medicine',
    quantity INT NOT NULL DEFAULT 0 COMMENT 'Quantity of medicine ordered',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Price per unit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_medicine (medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Order items table';

