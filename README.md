<div align="center">
  <img src="https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=0,99,153,102,51&height=220&section=header&text=Collective+Data+Portal&fontSize=70&fontAlignY=40&animation=twinkling&fontColor=ffffff" width="100%" alt="Header" />
  <h2 style="margin-top:-30px; color:#6366f1; font-family:'Fira Code', monospace;">Advanced Sales Reporting & Analytics Platform</h2>
</div>

<br />

<div align="center">
  <img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/MongoDB-47A248?style=for-the-badge&logo=mongodb&logoColor=white" alt="MongoDB" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
  <img src="https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white" alt="jQuery" />
</div>

<br />

## About the Project

Collective Data Portal is a powerful, reporting-focused web application built for a Canada-based client. It consolidates and analyzes product sales data from **5 different provinces**, each with unique Excel file formats.

The system automates the entire data workflow: ingestion, normalization, storage, and visualization — transforming scattered data into clear, actionable business insights.

## Key Features

- Automated parsing and standardization of Excel files with varying structures
- Dual-database architecture: MySQL for structured data + MongoDB for flexible storage
- Comprehensive dashboards showing market share, sales incentives, and loss analysis
- Interactive filters, dynamic charts, and export options using JavaScript/jQuery
- High performance and data accuracy through testing and optimization

## Tech Stack

- **Backend**: Laravel (PHP) — Handles logic, APIs, and file processing
- **Databases**: MySQL
- **Frontend**: JavaScript, jQuery, HTML5, CSS3
- **Tools**: Git, Composer, npm

## Installation & Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/MoeezAhmedDar/collective-data-portal.git
   cd collective-data-portal
   
2. Install dependencies:
   ```bash
    composer install
    npm install
   
3. Copy .env.example to .env and configure your database:
   ```bash
   cp .env.example .env
    
4. Generate application key:
   ```bash
    php artisan key:generate
   
5. Run migrations & seed (if needed):
   ```bash
    php artisan migrate

6. Compile assets and start server:
   ```bash
    npm run dev
    php artisan serve
   
## Project Goals & Impact

- Consolidated siloed sales data from multiple provinces into one unified platform
- Enabled accurate cross-province comparisons and market share analysis
- Delivered high-value business intelligence through intuitive dashboards
- Improved data accuracy and operational decision-making for the client
