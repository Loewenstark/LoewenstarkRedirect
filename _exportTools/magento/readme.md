1. Copy the "url_export.php" to the magento root.
2. Open the file via cli or browser. A new file will be created: "url_export.csv"
3. Copy the "url_export.csv" file on the shopware-server
4. Use the SQL Query below to import the CSV data into the database. (Change the path ;))

LOAD DATA LOCAL INFILE  
'/home/leon/htdocs/offgridtec/product_url_export.csv'
INTO TABLE s_loeredirect_old_urls  
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(pid, sku, url);