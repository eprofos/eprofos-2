# Document UI Fixtures Documentation

## Overview

The Document UI fixtures provide a comprehensive set of UI templates and components for the EPROFOS document management system. These fixtures create professional, ready-to-use document layouts for various document types with pre-configured styling and component structures.

## Created Fixtures

### DocumentUITemplateFixtures

Creates **8 UI templates** with different layouts and purposes:

1. **Template Standard EPROFOS** (`standard-eprofos`)
   - Global template (can be used for any document type)
   - Default template for general documents
   - Professional layout with header, footer, and page numbering
   - Blue color scheme (#007bff)

2. **Template Papier à En-tête** (`letterhead-official`)
   - Specific to Legal documents
   - Official letterhead design with company branding
   - Formal correspondence layout
   - Red color scheme (#dc3545)

3. **Template Certificat** (`certificate-professional`)
   - Specific to Training materials
   - Landscape orientation for certificates
   - Decorative borders and elegant typography
   - Gold color scheme (#ffc107)

4. **Template Document Légal** (`legal-document`)
   - Specific to Legal documents (default for this type)
   - Structured layout with legal references
   - Document numbering and version control
   - Red color scheme (#dc3545)

5. **Template Livret d'Accueil** (`handbook-welcome`)
   - Specific to Student handbooks (default for this type)
   - Colorful and welcoming design
   - Student-friendly layout with info boxes
   - Blue color scheme (#007bff)

6. **Template Qualité Qualiopi** (`quality-qualiopi`)
   - Specific to Quality documents (default for this type)
   - Qualiopi-compliant design with certification badges
   - Quality indicators and audit information
   - Yellow color scheme (#ffc107)

7. **Template Minimal** (`minimal-clean`)
   - Global template for simple documents
   - Clean, no-frills design without headers/footers
   - Gray color scheme (#6c757d)

8. **Template Rapport d'Activité** (`activity-report`)
   - Specific to Procedure documents
   - Professional report layout with KPI sections
   - Chart placeholders and executive summary
   - Green color scheme (#28a745)

### DocumentUIComponentFixtures

Creates **54 UI components** distributed across all templates:

#### Component Types Used:
- **Text components**: Titles, content, contact info
- **Logo components**: Company logos and branding
- **Date components**: Document dates and timestamps
- **Page number components**: Pagination
- **Signature components**: Digital signatures and authorizations
- **Custom HTML components**: Complex layouts and styled sections
- **List components**: Table of contents and structured lists

#### Component Zones:
- **Header**: Logos, company info, document references
- **Body**: Main content, titles, structured information
- **Footer**: Contact info, pagination, legal mentions

## Template Features

### Page Configuration
- **Paper sizes**: A4 (portrait/landscape), A3, A5, Letter, Legal
- **Margins**: Customizable margins (15-40mm)
- **Orientation**: Portrait or landscape
- **Page numbering**: Configurable position and format

### Styling System
- **CSS integration**: Complete CSS styling for each template
- **Color schemes**: Template-specific color palettes
- **Typography**: Professional font choices and hierarchy
- **Responsive design**: Adaptable layouts

### Variable System
Each template supports dynamic variables:
- Company information (name, address, phone, email)
- Document metadata (title, date, version)
- Content placeholders
- Signature information
- Template-specific variables

### Example Variables:
```php
'company_logo' => ['type' => 'image', 'default' => '/images/logo-eprofos.png']
'company_name' => ['type' => 'text', 'default' => 'EPROFOS']
'document_reference' => ['type' => 'text', 'default' => '']
'effective_date' => ['type' => 'date', 'default' => '']
```

## Component Features

### Data Binding
Components can bind to dynamic data sources:
```php
'dataBinding' => [
    'src' => 'company.logo',
    'alt' => 'company.name'
]
```

### Conditional Display
Components can show/hide based on conditions:
```php
'conditionalDisplay' => [
    ['field' => 'document.title', 'operator' => 'not_empty', 'value' => '']
]
```

### Style Configuration
Components have configurable styling:
```php
'styleConfig' => [
    'font-size' => '18pt',
    'font-weight' => 'bold',
    'color' => '#007bff',
    'text-align' => 'center'
]
```

## Usage Statistics

The fixtures include realistic usage statistics:
- **Standard template**: 15 uses (most popular)
- **Certificate template**: 12 uses
- **Letterhead template**: 8 uses
- **Legal template**: 6 uses
- **Handbook template**: 4 uses
- **Quality template**: 3 uses
- **Minimal template**: 2 uses
- **Report template**: 1 use

## Template Relationships

### Document Type Associations:
- **Legal documents**: Letterhead and Legal templates
- **Training materials**: Certificate template
- **Student handbooks**: Handbook template
- **Quality documents**: Quality template
- **Procedures**: Report template
- **Global templates**: Standard and Minimal (any document type)

## Database Structure

### Tables Created:
- `document_ui_templates`: 8 records
- `document_ui_components`: 54 records

### Key Relationships:
- Templates → Document Types (optional, for type-specific templates)
- Templates → Components (one-to-many)
- Templates → Users (created_by, updated_by)

## Component Distribution by Template:

1. **Standard Template**: 7 components (header: 3, body: 2, footer: 2)
2. **Letterhead Template**: 6 components (header: 2, body: 4, footer: 1)
3. **Certificate Template**: 9 components (header: 3, body: 5, footer: 2)
4. **Legal Template**: 6 components (header: 2, body: 3, footer: 2)
5. **Handbook Template**: 7 components (header: 1, body: 4, footer: 2)
6. **Quality Template**: 7 components (header: 2, body: 4, footer: 2)
7. **Report Template**: 7 components (header: 2, body: 4, footer: 2)
8. **Minimal Template**: No components (handled by base template)

## Professional Templates Available

### Certificate Template Features:
- Landscape orientation for formal certificates
- Decorative gold borders
- Professional typography hierarchy
- Signature blocks with validation
- Completion date and duration tracking

### Legal Document Features:
- Document reference numbering
- Legal citation formatting
- Version control and effective dates
- Confidentiality markings
- Structured article formatting

### Quality Template Features:
- Qualiopi certification badges
- Audit information tracking
- Compliance indicators
- Quality metrics display
- Continuous improvement notes

### Report Template Features:
- Executive summary sections
- KPI indicator boxes
- Chart/graph placeholders
- Author and department tracking
- Confidential document marking

## Integration Notes

These fixtures integrate seamlessly with:
- Document Type system (type-specific templates)
- User management (creation tracking)
- PDF generation (page configuration)
- Variable replacement system
- Component rendering engine

## Customization

Templates are fully customizable:
- **CSS styling**: Complete control over appearance
- **HTML structure**: Flexible layout system
- **Variable definitions**: Dynamic content integration
- **Component configuration**: Modular design approach
- **Conditional rendering**: Smart content display

## Testing and Validation

All fixtures have been tested for:
- ✅ Database integrity
- ✅ Relationship consistency
- ✅ Template compilation
- ✅ Component rendering
- ✅ Variable substitution
- ✅ Style application

## Future Enhancements

The fixture system supports future additions:
- Additional template types
- New component categories
- Enhanced styling options
- Advanced conditional logic
- Multi-language support
- Template inheritance

This comprehensive fixture set provides a solid foundation for professional document generation in the EPROFOS platform, with templates covering all major use cases and document types.
