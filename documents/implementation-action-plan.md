# Treatment Planner Implementation Action Plan

**Date:** June 26, 2025  
**Project:** OpenEMR Treatment Planner Module - Data-Driven AI Implementation  
**Approach:** Extract LBF_MHINTAKE assessment data → Generate AI prompts → Create treatment plans

## Current Status

### ✅ Completed Components
- Database schema (treatment_plans, usage_tracking, templates tables)
- Core services (TreatmentService, OpenAIService, ConfigService, UsageService)
- Basic controller structure (TreatmentController)
- Twig templates (view, edit, create, list)
- Module configuration and bootstrap files

### ❌ Missing Components
- Assessment data extraction service
- OpenEMR integration entry points
- Template-free implementation (currently has template dependencies)
- Dynamic AI prompt generation based on assessment data

## Action Plan

### Step 1: Create Assessment Data Extraction Service
**File:** `src/Services/AssessmentService.php`
**Purpose:** Extract and structure data from LBF_MHINTAKE form

**Tasks:**
- [ ] Create service class with methods to extract 16 assessment sections
- [ ] Implement data parsing for Mental Status Examination (13 categories)
- [ ] Add methods for clinical assessment data extraction
- [ ] Structure data for AI prompt generation
- [ ] Add error handling and validation

**Key Methods:**
- `extractAssessmentData($pid, $encounter)` - Main extraction method
- `parseMentalStatusExam($data)` - Parse MSE data
- `extractPresentingProblem($data)` - Get presenting problem
- `extractRiskAssessment($data)` - Get risk factors
- `formatForAI($data)` - Structure data for AI consumption

### Step 2: Remove Template Dependencies
**Files to modify:**
- `src/Controllers/TreatmentController.php`
- `templates/treatment/create.html.twig`
- Remove `src/Services/TemplateService.php` references

**Tasks:**
- [ ] Remove template selection from create form
- [ ] Remove TemplateService from TreatmentController
- [ ] Simplify create action to use assessment data only
- [ ] Update create template to remove template dropdown

### Step 3: Implement Dynamic AI Prompt Generation
**File:** `src/Services/PromptService.php`
**Purpose:** Generate AI prompts based on assessment data

**Tasks:**
- [ ] Create PromptService class
- [ ] Build dynamic prompt templates based on assessment data
- [ ] Include evidence-based treatment guidelines
- [ ] Add prompt optimization for different clinical scenarios
- [ ] Integrate with OpenAIService

**Key Methods:**
- `generateTreatmentPrompt($assessmentData)` - Main prompt generation
- `buildClinicalContext($data)` - Create clinical context
- `addEvidenceBasedGuidelines($conditions)` - Add treatment guidelines
- `optimizePrompt($prompt)` - Optimize for AI processing

### Step 4: Create OpenEMR Integration Entry Points
**Files to create:**
- `treatment_create.php` - Entry point from encounter
- `treatment_list.php` - List patient treatment plans
- `treatment_view.php` - View specific plan
- `treatment_edit.php` - Edit plan

**Tasks:**
- [ ] Create encounter integration entry point
- [ ] Add menu items to OpenEMR interface
- [ ] Implement proper authentication and session handling
- [ ] Add navigation between treatment plan functions

### Step 5: Update TreatmentService Integration
**File:** `src/Services/TreatmentService.php`
**Purpose:** Integrate with new AssessmentService and PromptService

**Tasks:**
- [ ] Modify createTreatmentPlan() to use AssessmentService
- [ ] Integrate PromptService for dynamic prompt generation
- [ ] Remove template-related parameters
- [ ] Add comprehensive error handling
- [ ] Implement data validation

### Step 6: Testing and Validation
**Tasks:**
- [ ] Test assessment data extraction with sample data
- [ ] Validate AI prompt generation
- [ ] Test treatment plan creation workflow
- [ ] Verify OpenEMR integration points
- [ ] Test error handling scenarios

## Implementation Priority

1. **HIGH PRIORITY** - Step 1: Assessment Data Extraction Service
2. **HIGH PRIORITY** - Step 2: Remove Template Dependencies  
3. **MEDIUM PRIORITY** - Step 3: Dynamic AI Prompt Generation
4. **MEDIUM PRIORITY** - Step 4: OpenEMR Integration Entry Points
5. **LOW PRIORITY** - Step 5: TreatmentService Integration Updates
6. **LOW PRIORITY** - Step 6: Testing and Validation

## Technical Considerations

### Data Security
- Ensure HIPAA compliance for AI processing
- Implement proper data sanitization
- Add audit logging for all AI interactions

### Performance
- Optimize database queries for assessment data extraction
- Implement caching for frequently accessed data
- Add response time monitoring

### Error Handling
- Graceful degradation if AI service unavailable
- Comprehensive error logging
- User-friendly error messages

## Success Criteria

- [ ] Treatment plans can be generated from assessment data without templates
- [ ] Integration works seamlessly within OpenEMR encounter workflow
- [ ] AI-generated plans are clinically relevant and comprehensive
- [ ] All existing functionality (view, edit, list) continues to work
- [ ] Performance meets acceptable standards (<5 seconds for plan generation)

## Notes

- **No Templates Approach:** User explicitly confirmed we are NOT using templates - everything is data-driven from LBF_MHINTAKE form
- **AI Model:** Using ChatGPT/OpenAI for treatment plan generation
- **Data Source:** LBF_MHINTAKE form with 16 comprehensive assessment sections
- **Integration:** Must work within existing OpenEMR encounter workflow

---

*This action plan will be updated as implementation progresses. Each completed step should be marked with ✅ and any issues or changes documented.*
