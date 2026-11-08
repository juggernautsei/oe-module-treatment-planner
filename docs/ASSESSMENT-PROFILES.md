# Assessment profiles and source tracing

A module administrator can choose one installed active layout-based form in Treatment Planner Configuration. The default remains LBF_MHINTAKE. This feature does not install a form or validate its clinical content.

The mapping is a JSON object from canonical Treatment Planner field IDs to local layout field IDs. For example:

```json
{"reason_visit":"chief_complaint","treatment_goals":"patient_goals"}
```

Unmapped identical field IDs continue to work. Explicit mappings take precedence over matching field IDs. Mapped source fields must exist and be active in the chosen layout. Unknown canonical identifiers and duplicate source aliases are rejected. Only recognized fields enter the prompt; arbitrary extra form fields do not.

The latest nondeleted saved form in the selected encounter is used, ordered by form date and record ID. Older forms are not combined. Source snapshots preserve form and record identifiers, capture time, canonical and local field IDs, resolved list labels, allowed demographic inputs, chart problem-list entries and additional observations. They are saved atomically with the generated plan. They are clinical data and must follow the same access and retention policies as the plan.

## Readiness check

A documented presenting concern is required. Missing or uncertain patient goals, risk assessment, medications and allergies are shown before generation. Explicit negatives and zero values are retained. Other gaps require acknowledgement; this is a completeness check, not a determination that the assessment is clinically sufficient. The source fingerprint binds the submitted preview to the inputs actually used; changed input requires a fresh review.

## What source references mean

S references identify assessment fields, P references identify allowed demographic inputs, D references identify chart problem-list entries and O1 identifies the submitted observations. The prompt asks the model to cite those identifiers and label suggestions or inferences. A citation only points to a recorded input. It does not prove the model's interpretation or establish clinical truth, and the module does not verify every generated claim automatically. The source review panel flags absent citations, unknown citation IDs and code-shaped values not found verbatim in the input. These are text-level warnings, not clinical or coding validation. Clinicians must compare the plan with the frozen source panel and the chart.

Existing plans have no retroactively invented source snapshot. Their view states that original inputs are unavailable.

## Canonical field IDs

- `reason_visit`
- `problem_start`
- `problem_duration`
- `problem_severity`
- `precipitating_factors`
- `current_stressors`
- `appearance_build`
- `appearance_hygiene`
- `appearance_dress`
- `appearance_grooming`
- `orientation`
- `consciousness`
- `alertness`
- `concentration`
- `attention`
- `memory_short`
- `memory_long`
- `intelligence`
- `eye_contact`
- `gait`
- `psychomotor`
- `cooperation`
- `speech_rate`
- `speech_volume`
- `speech_content`
- `speech_fluency`
- `mood`
- `affect_range`
- `affect_amplitude`
- `affect_appropriate`
- `thought_organization`
- `thought_coherence`
- `thought_goal_directed`
- `delusions`
- `obsessions`
- `phobias`
- `suicidal_ideation`
- `homicidal_ideation`
- `hallucinations_auditory`
- `hallucinations_visual`
- `hallucinations_other`
- `insight`
- `judgment`
- `suicide_current`
- `suicide_plan`
- `suicide_means`
- `suicide_history`
- `suicide_protective`
- `violence_current`
- `violence_history`
- `violence_triggers`
- `substance_current`
- `substances_used`
- `substance_frequency`
- `substance_impact`
- `psychiatric_history`
- `medical_history`
- `family_psych_history`
- `current_medications`
- `allergies`
- `work_functioning`
- `social_functioning`
- `family_functioning`
- `self_care`
- `adl_description`
- `primary_diagnosis`
- `secondary_diagnoses`
- `rule_out_diagnoses`
- `diagnosis_severity`
- `previous_therapy`
- `previous_medications`
- `psychiatric_hospitalizations`
- `treatment_response`
- `living_situation`
- `support_system`
- `education_level`
- `employment_status`
- `financial_stressors`
- `legal_issues`
- `cultural_factors`
- `personal_strengths`
- `coping_skills`
- `motivation_change`
- `treatment_goals`
- `available_resources`
