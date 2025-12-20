-- This script creates the 'invoices' table in the SQL Server database.
-- It's designed to store invoice details transferred from the MySQL database.

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='invoices' and xtype='U')
BEGIN
    CREATE TABLE invoices (
        id INT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        plate_number VARCHAR(255),
        vin VARCHAR(255),
        created_at DATETIME,
        invoice_data NVARCHAR(MAX),
        fina_status BIT DEFAULT 0,
        transferred_at DATETIME DEFAULT GETDATE()
    );
END
