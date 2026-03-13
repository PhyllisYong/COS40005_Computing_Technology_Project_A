COS40005_Computing_Technology_Project_A

This is the repository for Group 12 of COS40005, Computing Technology Project A, also known as FYP-A. 

# Initial Setup

1. run 'composer install' to download all available dependencies for the backend
2. run 'npm install' for all frontend dependencies such as React and other libraries
3. run 'cp .env.example .env' to get a copy of the Laravel environment file

3.1. check and edit configuration for database connection as well as ensure that XAMPP is running the local MySQL server

4. generate an encryption key by running 'php artisan key:generate'

## Database Setup

1. 'php artisan migrate' creates the database schema
2. 'php artisan migrate:fresh' drops whatever schema that has been created and reruns all migrations

! please note that all migration files are ran in the order that you see in the file directory, from earliest timestamp to latest. this means that 
you can change the order of migrations by simply changing the timestamp of the migration file !
! tables with foreign keys need to have those foreign keys declared FIRST in ANOTHER migration before being used in that current one!
! you can make new migration file with 'php artisan make:migration create<table_name>. for example, 'php artisan make:migration create_inferences_table'.

# Running the Application

1. run 'php artisan serve' for the backend
2. run 'npm run dev' for the frontend