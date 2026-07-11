# DataForge CRM

## Product Vision

DataForge CRM is a modern Customer Relationship Management (CRM) system designed for businesses with field sales representatives.

The goal is to help sales representatives manage their assigned customers, organize customer visits, maintain customer relationships, and record all interactions in one place.

This project should feel like a real SaaS product rather than a CRUD application.

The backend will be built with Laravel and PostgreSQL, the web frontend with Vue 3 + TypeScript, and a companion Flutter mobile application will use the same REST API.

---

# Design Principles

- Build as if this will be used by a real company.
- Keep the MVP simple but scalable.
- Follow Laravel and Vue best practices.
- Use RESTful API design.
- Security first (authentication, authorization, validation).
- Every feature should solve a real business problem.
- Avoid unnecessary complexity in the MVP.
- Design the architecture so future features can be added without major refactoring.

---

# User Roles

## Administrator

Responsible for maintaining the system.

Can:

- Manage users
- Assign customers to sales representatives
- View all customers
- View reports
- Manage application settings

---

## Sales Representative

Represents customers in the field.

Can:

- View only assigned customers
- Create visit plans
- Record customer visits
- Upload photos/documents
- Add notes
- Manage follow-up tasks
- View own dashboard

Sales Representatives must NEVER be able to view customers assigned to other representatives.

---

# Web Application

## Authentication

- Login
- Forgot Password
- Change Password
- User Profile

---

## Dashboard

Administrator Dashboard

- Total Customers
- Total Sales Representatives
- Upcoming Visits
- Recent Activities
- Task Summary

Sales Representative Dashboard

- Assigned Customers
- Today's Visits
- Upcoming Visits
- Pending Tasks
- Recent Customer Activities

---

## Customer Management

Each customer contains:

- Company Information
- Contact Persons
- Phone Numbers
- Email Addresses
- Physical Address
- Notes
- Attachments
- Assigned Sales Representative

Customer Profile should display a complete activity timeline.

---

## Customer Timeline

Every interaction should be recorded chronologically.

Examples:

- Customer Created
- Visit Completed
- Notes Added
- Attachment Uploaded
- Follow-up Scheduled
- Task Completed

The timeline becomes the customer's history.

---

## Weekly Coverage Plan

Sales Representatives can plan customer visits for the week.

Example:

Monday

- ABC Corporation
- XYZ Hardware

Tuesday

- Metro Pharmacy

Wednesday

- Fresh Market

The calendar should be simple and easy to use.

Future versions may include approvals.

---

## Customer Visits

Sales Representatives should be able to:

- Start Visit
- Record visit notes
- Upload photos
- Upload attachments
- Finish Visit

Each completed visit is automatically added to the customer's timeline.

---

## Tasks

Sales Representatives can create follow-up tasks.

Examples:

- Call Customer
- Send Proposal
- Follow-up Visit
- Collect Documents

Task Status

- Pending
- In Progress
- Completed

Tasks may optionally be linked to a customer.

---

## Documents

Each customer can have documents attached.

Examples:

- Contracts
- Photos
- Quotations
- Product Catalogs
- Other Files

---

## Notifications

Simple notification system.

Examples:

- Upcoming visit
- Task due today
- Customer added
- Follow-up reminder

---

## Reports (MVP)

Administrator

- Customer Count
- Customers per Sales Representative
- Visits Completed
- Tasks Completed

Sales Representative

- My Visits
- My Customers
- My Completed Tasks

---

# Mobile Application (Flutter)

The mobile application is designed primarily for field sales representatives.

The experience should be fast, simple, and mobile-first.

---

## Dashboard

Displays:

- Today's Visits
- Upcoming Visits
- Pending Tasks

---

## My Customers

Displays only customers assigned to the logged-in sales representative.

Customer card includes:

- Name
- Address
- Contact
- Next Visit
- Quick Actions

---

## Customer Details

View

- Customer Information
- Timeline
- Notes
- Documents
- Upcoming Tasks
- Visit History

---

## Weekly Plan

Displays scheduled customer visits for the week.

Sales representatives can quickly see who they need to visit each day.

---

## Visit Workflow

Simple workflow:

Start Visit

↓

Add Notes

↓

Take Photos (optional)

↓

Upload Attachments (optional)

↓

Finish Visit

The completed visit is synchronized to the web application.

---

## Tasks

View assigned tasks.

Mark tasks as completed.

Create follow-up tasks while visiting customers.

---

## Offline Ready (Future)

The architecture should allow future support for offline mode.

Although not implemented in the MVP, the mobile application should be designed with synchronization in mind.

---

# Future Features (Not MVP)

These features should influence the architecture but should NOT be implemented yet.

- Sales Manager hierarchy
- Territory Management
- Customer Transfer
- GPS Check-in
- Customer Signatures
- Push Notifications
- Calendar Integration
- AI Meeting Summaries
- AI Customer Insights
- Multi-tenancy
- Offline Synchronization
- Sales Pipeline
- Quotations
- Orders
- Email Integration

---

# Technical Goals

The project should demonstrate:

- Clean Architecture
- Feature-based organization
- SOLID principles
- Repository / Service pattern where appropriate
- Laravel Policies & Gates
- Role-Based Access Control (RBAC)
- REST API best practices
- Vue 3 Composition API
- TypeScript
- PostgreSQL
- Docker
- Unit & Feature Testing
- Responsive Design
- Reusable Components
- Proper error handling
- Audit logging for important actions

---

# Overall Goal

The objective is to build a professional portfolio project that resembles a real enterprise CRM used by field sales teams. Every feature should have a clear business purpose, and the system should be designed to grow into a full Sales Force Automation platform over time while keeping the MVP focused, clean, and maintainable.
