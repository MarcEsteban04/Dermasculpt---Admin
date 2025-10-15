# AI Skin Analysis Setup Guide

## Overview
The AI Skin Analysis feature allows dermatologists to upload skin images and receive AI-powered diagnostic assistance using Google's Gemini 2.0 Flash model with evidence-based dermatology knowledge.

## Features
- **Image Upload**: Drag-and-drop or click to upload skin images (JPG, PNG, GIF, WebP up to 10MB)
- **Patient Information**: Record patient name, age, and gender for context
- **AI Analysis**: Powered by Gemini 2.0 Flash with comprehensive dermatology knowledge base
- **Evidence-Based**: Uses knowledge from Mayo Clinic, AAD, DermNet, and other reputable sources
- **Differential Diagnosis**: Provides multiple possible conditions with confidence scores
- **Clinical Review**: Dermatologists can add their own diagnosis and notes
- **Status Tracking**: Track analysis status (pending, reviewed, confirmed, rejected)

## Setup Instructions

### 1. Database Setup
Run the SQL file to create the required table:
```sql
-- Execute this in your MySQL database
SOURCE /path/to/skin_analysis_table.sql;
```

Or manually execute:
```sql
CREATE TABLE `skin_analysis` (
  `analysis_id` int(11) NOT NULL AUTO_INCREMENT,
  `dermatologist_id` int(11) NOT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `patient_age` int(3) DEFAULT NULL,
  `patient_gender` enum('Male','Female','Other') DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `analysis_prompt` text DEFAULT NULL,
  `ai_diagnosis` longtext DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `detected_conditions` json DEFAULT NULL,
  `recommendations` longtext DEFAULT NULL,
  `dermatologist_notes` text DEFAULT NULL,
  `dermatologist_diagnosis` text DEFAULT NULL,
  `status` enum('pending','reviewed','confirmed','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`analysis_id`),
  KEY `dermatologist_id` (`dermatologist_id`),
  CONSTRAINT `skin_analysis_ibfk_1` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE
);
```

### 2. Directory Permissions
Ensure the upload directory has proper permissions:
```bash
mkdir -p uploads/skin_analysis
chmod 755 uploads/skin_analysis
```

### 3. API Configuration
The Gemini API is already configured with:
- **API Key**: AIzaSyDNjE4Ws4MGvvFqnxrH6q0sxOuGZpDCS98
- **Model**: gemini-2.0-flash
- **Endpoint**: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent

## File Structure
```
pages/
├── skin_analysis.php          # Main analysis page
backend/
├── skin_analysis_api.php      # AI analysis processing
├── get_analysis.php           # Retrieve analysis results
├── update_analysis.php        # Update dermatologist review
└── get_recent_analyses.php    # Get recent analyses list
db/
└── skin_analysis_table.sql    # Database schema
uploads/
└── skin_analysis/             # Image storage directory
```

## Knowledge Base Sources

The AI uses evidence-based knowledge from:

### Primary Sources
- **Mayo Clinic**: Comprehensive medical information and diagnostic criteria
- **American Academy of Dermatology (AAD)**: Professional dermatology guidelines
- **DermNet**: Extensive dermatology image database and information
- **UpToDate**: Evidence-based clinical decision support
- **New England Journal of Medicine (NEJM)**: Peer-reviewed medical research

### Covered Conditions
- Acne Vulgaris
- Atopic Dermatitis (Eczema)
- Psoriasis
- Seborrheic Dermatitis
- Rosacea
- Melanoma (ABCDE criteria)
- Basal Cell Carcinoma
- Squamous Cell Carcinoma
- And many more...

### Diagnostic Criteria
- Morphology assessment (macule, papule, plaque, nodule, etc.)
- Distribution patterns
- Color variations
- Red flag identification
- Treatment recommendations

## Usage Workflow

### 1. Upload Image
- Navigate to "Skin Analysis" in the sidebar
- Enter patient information (optional but recommended)
- Upload skin image via drag-and-drop or file selection
- Add analysis focus notes if needed

### 2. AI Analysis
- Click "Analyze with AI"
- System processes image with Gemini API
- AI provides:
  - Primary diagnosis
  - Confidence score
  - Differential diagnoses with probabilities
  - Clinical recommendations
  - Red flags and follow-up suggestions

### 3. Clinical Review
- Review AI analysis results
- Add clinical diagnosis
- Include clinical notes and observations
- Update status (pending/reviewed/confirmed/rejected)
- Save review

### 4. Status Tracking
- Monitor analysis status in dashboard
- View statistics by status type
- Access analysis history

## Security Features

### Image Validation
- File type validation (JPG, PNG, GIF, WebP only)
- File size limit (10MB maximum)
- Unique filename generation to prevent conflicts

### Access Control
- Session-based authentication required
- Dermatologist-specific data isolation
- Foreign key constraints for data integrity

### API Safety
- Gemini API safety settings enabled
- Content filtering for harmful content
- Error handling and logging

## API Response Format

The AI returns structured JSON responses:
```json
{
  "primary_diagnosis": "Most likely condition",
  "confidence_score": 85,
  "differential_diagnoses": [
    {
      "condition": "Diagnosis 1",
      "probability": 85,
      "rationale": "Clinical reasoning"
    }
  ],
  "visual_findings": "Detailed lesion description",
  "recommendations": "Clinical recommendations",
  "red_flags": "Concerning features",
  "patient_education": "Educational information",
  "follow_up": "Recommended timeline"
}
```

## Troubleshooting

### Common Issues
1. **Upload fails**: Check directory permissions and file size
2. **API errors**: Verify API key and internet connection
3. **Database errors**: Ensure table exists and foreign keys are valid
4. **Image not displaying**: Check file path and web server configuration

### Error Logs
Check browser console and server logs for detailed error messages.

## Limitations and Disclaimers

### Important Notes
- **Educational Purpose**: AI analysis is for educational and diagnostic assistance only
- **Clinical Correlation**: Always correlate AI findings with clinical examination
- **Not a Replacement**: Does not replace professional medical judgment
- **Accuracy**: AI accuracy depends on image quality and clinical context
- **Legal Compliance**: Ensure compliance with local medical regulations

### Best Practices
- Use high-quality, well-lit images
- Include relevant clinical history
- Always perform clinical correlation
- Document dermatologist review
- Follow institutional guidelines for AI assistance

## Support and Updates

For technical support or feature requests, refer to the development team. The knowledge base is regularly updated with the latest dermatology research and guidelines.
