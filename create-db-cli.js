const mysql = require('mysql2');
const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Default MAMP MySQL settings
const defaultConfig = {
  host: 'localhost',
  port: 8889,
  user: 'root',
  password: 'root'
};

console.log('Local WordPress Database Creator');
console.log('----------------------------------');

rl.question('Enter database name: ', (dbName) => {
  rl.question(`Enter MySQL port (default ${defaultConfig.port}): `, (port) => {
    port = port || defaultConfig.port;
    
    rl.question(`Enter MySQL username (default ${defaultConfig.user}): `, (user) => {
      user = user || defaultConfig.user;
      
      rl.question(`Enter MySQL password (default ${defaultConfig.password}): `, (password) => {
        password = password || defaultConfig.password;
        
        const connection = mysql.createConnection({
          host: defaultConfig.host,
          port: port,
          user: user,
          password: password
        });

        connection.connect((err) => {
          if (err) {
            console.error('Error connecting to MySQL:', err);
            rl.close();
            return;
          }
          
          console.log('Connected to MySQL server');
          
          connection.query(`CREATE DATABASE IF NOT EXISTS ${dbName}`, (err) => {
            if (err) {
              console.error('Error creating database:', err);
            } else {
              console.log(`Database '${dbName}' created successfully`);
              console.log(`\nYou can now use these details in your wp-config.php:`);
              console.log(`define('DB_NAME', '${dbName}');`);
              console.log(`define('DB_USER', '${user}');`);
              console.log(`define('DB_PASSWORD', '${password}');`);
              console.log(`define('DB_HOST', 'localhost:${port}');`);
            }
            
            connection.end((err) => {
              if (err) console.error('Error closing connection:', err);
              rl.close();
            });
          });
        });
      });
    });
  });
});