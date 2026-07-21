<?php
require_once __DIR__ . '/db.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS job_applications (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        father_name VARCHAR(255),
        job_location VARCHAR(255),
        city VARCHAR(100),
        mobile VARCHAR(20),
        interview_date DATE,
        interview_time TIME,
        resume_path VARCHAR(255),
        kyc_path VARCHAR(255),
        status VARCHAR(50) DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    )
");

echo "✅ Table 'job_applications' created successfully!";
?>
