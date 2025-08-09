<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/wamp64/www/lars/php_errors.log');
error_reporting(E_ALL);
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - LARS Staff Dashboard</title>
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/staff/help.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/zaqa-logo.png">
</head>
<body>
<?php include 'generic/navbar.php'; ?>
<div class="help-container">
    <?php include 'generic/sidebar.php'; ?>
    <main class="help-content">
        <main class="dashboard-content">
            <div class="welcome-section">
                <h1><i class="fas fa-question-circle"></i> Help & Support</h1>
                <p>Learn how to use the Learner Records System effectively</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card highlight">
                    <h3><i class="fas fa-graduation-cap"></i></h3>
                    <p><i class="fas fa-university"></i> ECZ Records</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-tools"></i></h3>
                    <p><i class="fas fa-certificate"></i> TEVETA Records</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-graduate"></i></h3>
                    <p><i class="fas fa-school"></i> Higher Education</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-camera"></i></h3>
                    <p><i class="fas fa-download"></i> Screenshot Feature</p>
                </div>
            </div>

            <div id="alertContainer"></div>

            <!-- Help Navigation Tabs -->
            <div class="dashboard-card">
                <div class="help-tabs">
                    <button class="tab-button active" onclick="showHelpSection('getting-started')">
                        <i class="fas fa-play-circle"></i> Getting Started
                    </button>
                    <button class="tab-button" onclick="showHelpSection('searching')">
                        <i class="fas fa-search"></i> How to Search
                    </button>
                    <button class="tab-button" onclick="showHelpSection('screenshots')">
                        <i class="fas fa-camera"></i> Taking Screenshots
                    </button>
                    <button class="tab-button" onclick="showHelpSection('troubleshooting')">
                        <i class="fas fa-tools"></i> Common Issues
                    </button>
                </div>
            </div>

            <!-- Getting Started Section -->
            <div id="getting-started" class="help-section dashboard-card">
                <h3><i class="fas fa-rocket"></i> Welcome to the Learner Records System</h3>

                <div class="help-content">
                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-info-circle"></i> What This System Does</h4>
                        </div>
                        <div class="help-text">
                            This system helps you find and view learner records from three main educational institutions in Zambia. You can search for certificates and qualifications from ECZ, TEVETA, and Higher Education Institutions. Each record you find can be easily saved as a screenshot for your files.
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-graduation-cap"></i> Types of Records Available</h4>
                        </div>
                        <div class="help-text">
                            <strong>ECZ (Examinations Council of Zambia):</strong> Grade 7, 9, and 12 examination certificates<br><br>
                            <strong>TEVETA:</strong> Technical and vocational training certificates, trade qualifications<br><br>
                            <strong>Higher Education:</strong> University degrees, diplomas, and professional qualifications
                        </div>
                    </div>
                </div>
            </div>

            <!-- Searching Section -->
            <div id="searching" class="help-section dashboard-card" style="display: none;">
                <h3><i class="fas fa-search"></i> How to Search for Learner Records</h3>

                <div class="help-content">
                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-map-signs"></i> Step 1: Navigate to the Right Search Page</h4>
                        </div>
                        <div class="help-text">
                            In the sidebar, click on "Learner Records" to see the dropdown menu, then choose the specific search page you need:<br><br>
                            • <strong>ECZ Search</strong> - For Grade 7, 9, and 12 examination certificates<br>
                            • <strong>TEVETA Search</strong> - For technical and vocational training certificates<br>
                            • <strong>Higher Education Search</strong> - For university degrees and diplomas<br><br>
                            <em>Each institution has its own dedicated search page with specific fields.</em>
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-list"></i> Step 2: Choose Your Search Category</h4>
                        </div>
                        <div class="help-text">
                            On each search page, click the dropdown menu that says "Select search criteria" and pick the best option:<br><br>
                            • <strong>Name</strong> - Search using the learner's full name<br>
                            • <strong>Candidate ID</strong> - Use the unique student identification number<br>
                            • <strong>Certificate Number</strong> - Enter the certificate reference number<br>
                            • <strong>NRC (First 6 digits)</strong> - Use the first 6 digits of the National Registration Card<br>
                            • <strong>Passport Number</strong> - For international students with passport records
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-keyboard"></i> Step 3: Enter Your Search Information</h4>
                        </div>
                        <div class="help-text">
                            After selecting your search method, carefully type the information in the search box:<br><br>
                            • <strong>For Names:</strong> Enter the full name as it appears on the certificate<br>
                            • <strong>For Numbers:</strong> Enter digits exactly as they appear on documents<br>
                            • <strong>Check Spelling:</strong> Make sure names are spelled correctly<br>
                            • <strong>Use Proper Case:</strong> Capitalize first letters of names<br><br>
                            Then click the search button to find matching records.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Screenshots Section -->
            <div id="screenshots" class="help-section dashboard-card" style="display: none;">
                <h3><i class="fas fa-camera"></i> How to Take Screenshots of Records</h3>

                <div class="help-content">
                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-mouse-pointer"></i> Finding the Screenshot Button</h4>
                        </div>
                        <div class="help-text">
                            When you find a learner record in your search results, look for the small camera icon (<i class="fas fa-camera"></i>) next to each record. This button lets you capture a screenshot of that specific record. You'll see it in the "Actions" column of your search results table.
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-file-signature"></i> Automatic File Naming</h4>
                        </div>
                        <div class="help-text">
                            When you click the camera icon, the system automatically creates a screenshot with a helpful filename. The file name includes:<br><br>
                            • The learner's name<br>
                            • The institution (ECZ, TEVETA, or Higher Education)<br>
                            • The qualification type<br>
                            • The file extension (.png)<br><br>
                            <strong>Example:</strong> "John_Doe_ECZ_Grade12_Certificate.png"
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-download"></i> Where Screenshots Are Saved</h4>
                        </div>
                        <div class="help-text">
                            Screenshots are automatically saved to your computer's Downloads folder. The images are high-quality and suitable for official use. You can then move or copy them to any folder you prefer for better organization.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting Section -->
            <div id="troubleshooting" class="help-section dashboard-card" style="display: none;">
                <h3><i class="fas fa-tools"></i> Common Issues and Solutions</h3>

                <div class="help-content">
                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-exclamation-triangle"></i> No Search Results Found</h4>
                        </div>
                        <div class="help-text">
                            If your search doesn't return any results, try these solutions:<br><br>
                            • <strong>Check spelling</strong> of names carefully<br>
                            • <strong>Try a different search page</strong> - Maybe the record is in ECZ instead of TEVETA<br>
                            • <strong>Use different search methods</strong> (try NRC instead of name)<br>
                            • <strong>Search with partial information</strong> (just first name or surname)<br>
                            • <strong>Verify the information</strong> with the learner or institution<br>
                            • <strong>Check all three search pages</strong> - ECZ, TEVETA, and Higher Education
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-camera-retro"></i> Screenshot Feature Not Working</h4>
                        </div>
                        <div class="help-text">
                            If you can't take screenshots of records:<br><br>
                            • <strong>Check browser permissions</strong> - Allow downloads when prompted<br>
                            • <strong>Clear browser cache</strong> and try again<br>
                            • <strong>Make sure you have storage space</strong> on your computer<br>
                            • <strong>Try refreshing the page</strong> and searching again<br>
                            • <strong>Use a different browser</strong> if the problem continues
                        </div>
                    </div>

                    <div class="help-item">
                        <div class="help-header">
                            <h4><i class="fas fa-headset"></i> Still Need Help?</h4>
                        </div>
                        <div class="help-text">
                            If you're still having trouble or need assistance with the system:<br><br>
                            • <strong>Contact our support team</strong> for technical assistance<br>
                            • <strong>Ask a colleague</strong> who has used the system before<br>
                            • <strong>Check with your IT department</strong> for technical issues<br>
                            • <strong>Report system errors</strong> so we can fix them quickly
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script>
            function showHelpSection(sectionId) {
                // Hide all help sections
                const sections = document.querySelectorAll('.help-section');
                sections.forEach(section => section.style.display = 'none');

                // Remove active class from all tabs
                const tabs = document.querySelectorAll('.tab-button');
                tabs.forEach(tab => tab.classList.remove('active'));

                // Show selected section
                const targetSection = document.getElementById(sectionId);
                if (targetSection) {
                    targetSection.style.display = 'block';
                }

                // Add active class to clicked tab
                event.target.classList.add('active');
            }
        </script>
    </main>
</div>

