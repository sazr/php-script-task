# User Upload Script

Upload user data from a CSV file to a MySQL database. AI has been used to assist with documenting the code and this readme. In a real world scenario I would not feed code into an AI or post on a site unless the company allows this and the code/data does not contain sensitive or privileged information.

## Usage

```
php user_upload.php [options]
```

### Options:

- `--file [csv file name]`: Specifies the name of the CSV file to be parsed.
- `--create_table`: Builds the MySQL users table. No further action will be taken.
- `--dry_run`: Runs the script without inserting data into the database. All other functions will be executed, but the database won't be altered.
- `-u`: MySQL username.
- `-p`: MySQL password.
- `-h`: MySQL host.
- `--help`: Displays help documentation.

## Functionality

### Create/rebuild users table

```
php user_upload.php --create_table -u root -p root -h localhost
```

This directive will:

- Create the database `website_users` if it does not exist. *I notice the task document didn't provide a directive to provide the database name. So I have made an assumption I need to create the database as part of the task.*  
- Drop the `users` table if it already exists  
- Create the `users` table  

*The user must specify the mysql host, username and password*

### Import/upload users from a CSV file into a MySQL database

```
php user_upload.php --file "PATH_TO_FILE" -u root -p root -h localhost
```

This directive will:

- Create the database `website_users` if it does not exist. *I notice the task documentation didn't instruct adding a database name command line argument. So I have made an assumption I need to create the database as part of the task.*  
- Create the `users` table if it doesn't exist 
- Parse the CSV file for users. If invalid data is found: the script exits without making any changes to the database  
- "Upsert" the user data into the `users` table. Ie, if an email already exists the other column details will be updated, else a new row is inserted into the table.

*The user must specify the mysql host, username and password*


### Parse the users CSV file but not make any database alterations

```
php user_upload.php --file "PATH_TO_FILE" --dry_run
```

This directive will:

- No SQL changes are applied/committed. Ie no database or table is created.  
- Parse the CSV file for users. If invalid data is found: the script exits without making any changes to the database  
- No SQL changes are applied/committed. Ie no rows are inserted/updated in the `users` table.

*The user must specify the file but the user does NOT need to specify the mysql host, username and password*

