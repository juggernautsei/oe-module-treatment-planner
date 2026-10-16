# Treatment Planner Analysis and Implementation Plan

**Date:** June 26, 2025  
**Project:** OpenEMR Treatment Planner Module Enhancement  
**Focus:** AI-Generated Treatment Plans Based on Assessment Data

## Overview

This document outlines the analysis and implementation plan for enhancing the treatment planner module to generate AI-powered treatment plans based on the mental health assessment and intake form data.

## Current State Analysis

### Form Structure: LBF_MHINTAKE (Assessment & Intake Form)

The mental health assessment form is comprehensive with **16 major sections**:

#### 1. Clinical Assessment Sections

**Presenting Problem**
- Start time
- Reason for today's visit (free text)

**Mental Status Examination (13 detailed categories):**

1. **General Appearance**
   - Build: Options include appropriate, disheveled, emaciated, etc.
   - Hygiene: Appropriate/Normal, Disheveled, Flamboyant, Poor Hygiene, etc.

2. **Sensorium**
   - Orientation: x3 (person/place/time), x2 variations, x1, completely disoriented
   - Consciousness: Alert, lethargic, stuporous, etc.

3. **Cognitive Function**
   - Concentration: Normal, impaired, distractible, etc.
   - Attention: Normal, poor, hypervigilant, etc.
   - Short Term Memory: Normal, impaired, fair
   - Long Term Memory: Normal, impaired, fair
   - Intelligence: Above average, average, borderline, below average

4. **Behavioral Observations**
   - Eye Contact: Normal, poor, excessive, avoidant
   - Gait: Normal, angry, slow, tics, other
   - Speech Rate: Normal, rapid, slow, pressured
   - Speech Volume: Normal, loud, soft, whispered
   - Speech Content: Normal, circumstantial, tangential, etc.

5. **Emotional State**
   - Affect Range: Full/Normal, constricted, flat, elated, excitable
   - Affect Amplitude: Normal/Appropriate, blunted, labile, inappropriate
   - Mood: Appropriate, angry, anxious, sad, irritable, hostile, frustrated, calm, euphoric, overwhelmed

6. **Thought Processes**
   - Obsessions: Yes/No
   - Compulsions: Yes/No
   - Phobias: Yes/No
   - Psychomotor Activity: Normal, agitation, retardation, clumsy, catatonic, restlessness
   - Thought Process: Normal/Logical, goal oriented, LOA, FOA, tangential, circumstantial, illogical, perseveration, blocking, disorganized, loose, flight of ideas, clanging, word salad
   - Thought Content: Normal, obsessive, ruminative, etc.

7. **Perceptual Disturbances**
   - Hallucinations: Absent, auditory, visual, olfactory, tactile
   - Grandiosity: Absent, assaultive, destructive, aggressive

8. **Cognition and Relatedness**
   - Cognition Insight: Intact/Normal, impaired, limited
   - Judgment: Intact/Normal, impaired, limited
   - Relatedness: Normal, withdrawn, intrusive, etc.

#### 2. Risk Assessment Section

- **Area of Risk**: Multiple risk categories
- **Severity of Risk**: Various severity levels
- **Risk Protections**: Available protective factors
- **Level of Risk**: Overall risk assessment
- **Patient Denial**: Whether patient denies all risk areas

#### 3. Biopsychosocial Assessment

Comprehensive history sections including:

- **Identification**: Age, ethnicity, religion, marital status, referral status
- **History of Present Problem**: Symptoms, onset, duration, frequency
- **Psychiatric History**: Prior episodes, diagnoses, treatments, hospitalizations
- **Trauma History**: Nature, timing, persons involved
- **Medical History**: Current/past conditions, treatments, allergies
- **Current Medications**: Medication, dosage, purpose, prescriber
- **Substance Use**: History, substances used, alcohol, tobacco, prescription drugs
- **Family History**: Relationships, family of origin
- **Family Medical & Psychiatric History**: Medical history, mental illness, diagnoses, suicide attempts
- **Developmental History**: Milestones, delays
- **Educational/Vocational History**: Education level, employment
- **Legal History**: Arrests, DUIs, incarceration, court issues
- **Social History**: Support system, relationships, community resources
- **Other Relevant Information**: Hobbies, leisure activities

## Data Retrieval Strategy

### Current Query Structure
```sql
SELECT ld.* 
FROM `lbf_data` ld 
JOIN forms f 
WHERE ld.form_id = f.form_id 
AND f.pid = 4 
AND f.encounter = 136;
```

### Enhanced Query for AI Processing
```sql
SELECT 
    ld.field_id,
    ld.field_value,
    lo.title as option_text,
    layout.title as question_text,
    layout.group_id,
    lgp.grp_title as section_title
FROM lbf_data ld 
JOIN forms f ON ld.form_id = f.form_id 
LEFT JOIN list_options lo ON ld.field_value = lo.option_id 
    AND lo.list_id = CONCAT('mental_status_', ld.field_id)
LEFT JOIN layout_options layout ON ld.field_id = layout.field_id 
    AND layout.form_id = 'LBF_MHINTAKE'
LEFT JOIN layout_group_properties lgp ON layout.group_id = lgp.grp_group_id 
    AND lgp.grp_form_id = 'LBF_MHINTAKE'
WHERE f.pid = ? 
AND f.encounter = ? 
AND ld.form_id = 'LBF_MHINTAKE'
ORDER BY layout.group_id, layout.seq;
```

## AI Implementation Plan

### Phase 1: Data Processing Pipeline

1. **Extract Assessment Data**
   - Query database for patient's assessment responses
   - Map field IDs to human-readable questions
   - Convert option IDs to descriptive text
   - Group responses by assessment sections

2. **Data Formatting for AI**
   - Structure data into logical sections
   - Highlight significant findings (non-normal responses)
   - Include relevant history and context
   - Format risk assessment clearly

### Phase 2: AI Prompt Engineering

**Prompt Structure:**
```
You are a licensed mental health professional creating a treatment plan based on a comprehensive assessment.

PATIENT INFORMATION:
- Demographics: [Age, gender, etc.]
- Presenting Problem: [Chief complaint]

MENTAL STATUS EXAMINATION:
[Structured summary of MSE findings, highlighting abnormal findings]

RISK ASSESSMENT:
[Risk factors, severity, protective factors]

BIOPSYCHOSOCIAL HISTORY:
[Relevant history organized by domain]

TASK: Create a comprehensive treatment plan including:
1. Primary Treatment Goals (3-5 specific, measurable objectives)
2. Recommended Interventions (therapy modalities, frequency)
3. Medication Considerations (if indicated)
4. Safety Planning (based on risk assessment)
5. Timeline and Milestones
6. Referrals and Resources

Please ensure recommendations are evidence-based and appropriate for the presenting concerns.
```

### Phase 3: Treatment Plan Output Format

**Structured Treatment Plan:**
- **Primary Diagnosis**: Based on assessment findings
- **Treatment Goals**: 
  - Short-term (1-3 months)
  - Long-term (6-12 months)
- **Interventions**:
  - Individual therapy (modality, frequency)
  - Group therapy (if appropriate)
  - Family/couples therapy (if indicated)
- **Medication Management**: If warranted
- **Safety Planning**: Crisis intervention strategies
- **Monitoring and Review**: Follow-up schedule
- **Resources and Referrals**: Community resources, specialists

### Phase 4: Quality Assurance and Integration

1. **Validation Framework**
   - Template validation against clinical guidelines
   - Required human clinician review and approval
   - Clear AI-generated content disclaimers
   - Version control and audit trail

2. **OpenEMR Integration**
   - Seamless workflow integration
   - Data security and HIPAA compliance
   - User interface for review and editing
   - Documentation in patient record

## Technical Implementation Considerations

### Security and Compliance
- Ensure HIPAA compliance for AI processing
- Secure API connections to AI services
- Data encryption in transit and at rest
- Audit logging for all AI interactions

### User Experience
- Intuitive interface for clinicians
- Easy editing and customization of AI suggestions
- Clear indication of AI-generated vs. human-authored content
- Integration with existing OpenEMR workflows

### Performance and Reliability
- Response time optimization
- Fallback procedures if AI service unavailable
- Error handling and user feedback
- Scalability considerations

## Next Steps

1. **Prototype Development**
   - Build data extraction module
   - Create AI prompt templates
   - Develop basic UI for review/editing

2. **Testing and Validation**
   - Test with sample assessment data
   - Clinical review of AI-generated plans
   - Refinement of prompts and templates

3. **Integration and Deployment**
   - Full OpenEMR module integration
   - User training and documentation
   - Phased rollout with monitoring

## Benefits and Expected Outcomes

- **Efficiency**: Reduced time for treatment plan creation
- **Consistency**: Standardized, evidence-based recommendations
- **Comprehensiveness**: Ensures all assessment areas are addressed
- **Quality**: AI assistance with human oversight maintains clinical standards
- **Documentation**: Improved treatment plan documentation and rationale

## Risks and Mitigation Strategies

- **Clinical Liability**: Require human clinician review and approval
- **AI Accuracy**: Continuous monitoring and refinement of AI outputs
- **Data Privacy**: Strict adherence to HIPAA and security protocols
- **User Adoption**: Comprehensive training and change management

---

*This document serves as the foundation for implementing AI-enhanced treatment planning in the OpenEMR treatment planner module. Regular updates will be made as the project progresses.*
