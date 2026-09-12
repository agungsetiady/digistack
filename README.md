# DigiStack 🎓

A modern, scalable **E-Course & Digital Learning Management System (LMS)** designed for structured online software development education. Built with integrated **AI Providers & Models** (OpenAI, Google Gemini, Groq Cloud) to assist in dynamic learning content generation, automated topic summarization (TL;DR), and comprehensive course management.

---

## 🏗️ Core Architecture & Tech Stack

* **Backend Environment**: PHP 8.2+
* **Database Engine**: MariaDB / MySQL 10.4+
* **Architecture Style**: RESTful API / Modular Content System
* **Content Generation**: Integrated Multi-Provider AI (OpenAI, Gemini, Groq)
* **Supported Formatting**: Markdown, LaTeX ($...$ and $$...$$), Code Syntax Highlighting

---

## 🗄️ Database Schema & Structure

The underlying relational database consists of six core tables:

              +-------------------+
              |   ai_providers    |
              +-------------------+
                        | 1
                        |
                        | N
              +-------------------+
              |     ai_models     |
              +-------------------+


### Table Breakdown

1. **`courses`**: Top-level educational programs (e.g., API Development, Mastering OOP).
2. **`modules`**: Chapters or thematic sections contained within a specific course.
3. **`topics`**: Individual sub-lessons and articles under each module.
4. **`topic_contents`**: Stores full lesson material in Markdown format, TL;DR summaries, and AI-generation metadata.
5. **`ai_providers`**: API service configurations for AI integrations (Groq, OpenAI, Google Gemini).
6. **`ai_models`**: Available LLM models, token bounds, temperatures, and default states.

---

## 🚀 Key Features

* **Structured Learning Hierarchy**: Organizes material seamlessly into `Course -> Module -> Topic -> Content`.
* **Multi-Provider AI Engine**: Configurable API endpoints and models supporting Groq Cloud, OpenAI GPT-4o, and Google Gemini.
* **Smart Content Summarization**: Automatic generation of TL;DR summaries for complex technical topics.
* **Markdown & Code Support**: Full rendering support for code blocks, architecture diagrams, tables, and technical documentation.
* **Stateless RESTful Design**: Built with modern Web API principles, DTO layers, and token security in mind.

---

## 🛠️ Installation & Setup Guide

### Prerequisites

* **PHP**: `>= 8.2`
* **Database**: MySQL `>= 8.0` or MariaDB `>= 10.4`
* **Web Server**: Apache / Nginx / XAMPP / Laragon

### Step-by-Step Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.comagungsetiady/digistack.git
   cd digistack

**Import Database**
* Open your database manager (e.g., phpMyAdmin, TablePlus, or MySQL CLI).
* Create a new database named digistack:
* SQLCREATE DATABASE digistack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
* Import the provided SQL dump file:Bashmysql -u root -p digistack < digistack.sql
* Configure Environment Parameters
* Update your database credentials and API keys in your environment configuration file (config.php or .env):


* DB_HOST=127.0.0.1
* DB_NAME=digistack
* DB_USER=root
* DB_PASS=

# AI Provider Keys
* OPENAI_API_KEY=YOUR_OPENAI_API_KEY_HERE
* GEMINI_API_KEY=YOUR_GEMINI_API_KEY_HERE
* GROQ_API_KEY=YOUR_GROQ_API_KEY_HERE

Verify Seeded Data The initial database migration automatically seeds foundational courses:
The application allows switching or defaulting between different AI providers for content assistance.
📖 This project is open-source and available under the MIT License.
