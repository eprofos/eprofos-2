# Course PDF Download Implementation

## Overview

This implementation adds PDF download functionality to the Course admin interface using KnpSnappyBundle. Each Course can now be exported as a beautifully formatted PDF document with all course details following the same design patterns used in the application.

## Features Added

### 1. PDF Download Route
- **Route**: `/admin/course/{id}/pdf`
- **Method**: GET
- **Controller**: `CourseController::downloadPdf()`
- **Response**: PDF file download with appropriate filename

### 2. PDF Template
- **Template**: `templates/admin/course/pdf.html.twig`
- **Styling**: Professional PDF layout with Tabler-inspired design
- **Content**: Comprehensive course information including Qualiopi requirements

### 3. UI Integration
- **Show Page**: PDF download button added to course detail page
- **Index Page**: PDF download button added to actions column in course list
- **Styling**: Uses Tabler CSS classes for consistency

## Implementation Details

### Controller Changes

#### New Dependencies
```php
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
```

#### Constructor Update
```php
public function __construct(
    private EntityManagerInterface $entityManager,
    private SluggerInterface $slugger,
    private Pdf $pdf
) {}
```

#### New PDF Route Method
```php
#[Route('/{id}/pdf', name: 'admin_course_pdf', methods: ['GET'])]
public function downloadPdf(Course $course): Response
{
    $html = $this->renderView('admin/course/pdf.html.twig', [
        'course' => $course,
    ]);

    $filename = sprintf(
        'cours-%s-%s.pdf',
        $course->getSlug(),
        (new \DateTime())->format('Y-m-d')
    );

    return new PdfResponse(
        $this->pdf->getOutputFromHtml($html, [
            'page-size' => 'A4',
            'margin-top' => '20mm',
            'margin-right' => '20mm',
            'margin-bottom' => '20mm',
            'margin-left' => '20mm',
            'encoding' => 'UTF-8',
            'print-media-type' => true,
            'no-background' => false,
            'lowquality' => false,
            'enable-javascript' => false,
            'disable-smart-shrinking' => true,
        ]),
        $filename
    );
}
```

### PDF Template Features

#### 1. Header Section
- Course title prominently displayed
- Professional subtitle
- Clean design with brand colors

#### 2. Course Summary
- General information grid
- Course type, duration, order, and status
- Hierarchy information showing formation path

#### 3. Pedagogical Content (Qualiopi Compliant)
- Learning objectives with numbered list
- Content outline and main content
- Prerequisites and learning outcomes
- Teaching and assessment methods
- Resources and materials
- Success criteria

#### 4. Activities Section
- List of exercises with type and difficulty
- List of QCMs with question counts
- Well-organized in a two-column layout

#### 5. Footer
- Generation timestamp
- Course creation and modification dates
- EPROFOS branding

### UI Changes

#### Show Template (`show.html.twig`)
Added PDF download button to the action bar:
```twig
<a href="{{ path('admin_course_pdf', {'id': course.id}) }}" class="btn btn-danger" target="_blank">
    <i class="fas fa-file-pdf"></i> Télécharger PDF
</a>
```

#### Index Template (`index.html.twig`)
Added PDF download button to actions column:
```twig
<a href="{{ path('admin_course_pdf', {'id': course.id}) }}" class="btn btn-sm btn-outline-danger" target="_blank" title="Télécharger PDF">
    <i class="fas fa-file-pdf"></i>
</a>
```

## PDF Configuration

The PDF generation uses these optimal settings:
- **Page Size**: A4
- **Margins**: 20mm on all sides
- **Encoding**: UTF-8 for proper French character support
- **Quality**: High quality with backgrounds enabled
- **JavaScript**: Disabled for security and performance

## File Naming Convention

PDF files are automatically named using the pattern:
```
cours-{course-slug}-{current-date}.pdf
```

Examples:
- `cours-simulation-d-environnement-2025-07-20.pdf`
- `cours-introduction-aux-bases-de-donnees-2025-07-20.pdf`

## Styling

The PDF template uses a comprehensive CSS framework that includes:

### Color Scheme
- **Primary**: #0d6efd (blue)
- **Success**: #28a745 (green)
- **Warning**: #ffc107 (yellow)
- **Danger**: #dc3545 (red)
- **Secondary**: #6c757d (gray)

### Typography
- **Font Family**: Arial, sans-serif
- **Base Size**: 12px
- **Line Height**: 1.5

### Layout Components
- Grid system for responsive layouts
- Card-based content sections
- Badge system for status indicators
- Icon integration for visual hierarchy

## Testing

A functional test has been created to verify PDF generation:
- **Test File**: `tests/Functional/Admin/CoursePdfTest.php`
- **Coverage**: PDF response validation, content type verification, filename checking

## Benefits

### For Administrators
- Quick access to formatted course information
- Professional documents for printing or sharing
- Consistent branding and layout
- All course details in one document

### For Compliance (Qualiopi)
- Complete pedagogical documentation
- Learning objectives clearly presented
- Assessment methods documented
- Professional format for audits

### For Training Materials
- Ready-to-print course summaries
- Standardized format across all courses
- Comprehensive content overview
- Easy sharing with stakeholders

## Technical Requirements

### Dependencies
- KnpSnappyBundle (already installed)
- wkhtmltopdf binary (configured in environment)
- FontAwesome icons for UI buttons

### Environment Variables
- `WKHTMLTOPDF_PATH`: Path to wkhtmltopdf binary
- `WKHTMLTOIMAGE_PATH`: Path to wkhtmltoimage binary

## Usage

### From Course Detail Page
1. Navigate to any course detail page
2. Click the red "Télécharger PDF" button
3. PDF will download automatically

### From Course List
1. Navigate to the course index page
2. Find the desired course in the table
3. Click the PDF icon in the actions column
4. PDF will download automatically

## Future Enhancements

Potential improvements could include:
- Batch PDF generation for multiple courses
- Custom PDF templates based on course type
- Integration with email system for automatic distribution
- QR codes for digital access
- Watermarking for security
- Multi-language support for international use

## Security Considerations

- PDFs are generated server-side with disabled JavaScript
- No sensitive data exposure in URLs
- Proper access control through existing admin authentication
- File naming prevents directory traversal attacks
- Memory management for large course content
