<?php
declare(strict_types=1);

/**
 * Free course content, batch 1: platform fundamentals.
 * Each course carries three lessons of roughly 260 words
 * (750+ words per course). Plain text; the LMS renders it
 * with nl2br, so blank lines mark paragraph breaks.
 */

return [
    1 => [
        'overview' => 'Coconut Grower Digital Onboarding is the mandatory first course for every registered grower on the NATCODEV platform. It walks you through completing your grower profile, proving your farm identity with the right documents, and using your wallet safely. By the end you will be able to manage your account, respond to verification requests, and get help from the support desk without depending on a middleman. No prior experience with digital tools is assumed.',
        'duration' => 90,
        'lessons' => [
            [
                'title' => 'Completing Your Grower Profile',
                'summary' => 'Every field on your profile has a purpose; complete it once, correctly.',
                'duration' => 30,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Your grower profile is the single authoritative identity used across cooperative disbursements, input supplies, and buyer traceability. An incomplete record stalls all approvals.[/TAKEAWAY]\n\nYour grower profile is the single record the cooperative, field agents, and buyers rely on. An incomplete profile is the most common reason applications stall in review, so treat the first login as a one-time setup session rather than something to finish later.\n\n[STEPS]1. Legal Identity Match: Enter your full legal name exactly as printed on your national identity card or voter card.\n2. Permanent Reachable Phone: Supply your personal primary line for login, SMS OTP verifications, and wallet transaction alerts.\n3. Geographic Alignment: Always pick your State and LGA from the official platform dropdowns.\n4. Farm Size & Stand Count: Provide realistic palm counts and acreage so input allocations match real farm needs.[/STEPS]\n\n[PROTIP]Never type State or LGA names in free text. Reports, farm input trucks, and field-agent assignments are aggregated strictly by system-standard LGA codes.[/PROTIP]\n\nFinally, review your farm details: farm size, main crops, and approximate stand count. These figures feed production forecasts and eligibility checks for input programs. Estimates are acceptable at onboarding, but update them after each harvest season so the cooperative can plan collection and input supply around your real capacity.",
            ],
            [
                'title' => 'Farm Identity and Documents',
                'summary' => 'Which documents prove your farm, and how to upload them so they pass review.',
                'duration' => 30,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:grower-onboarding-workflow|Coconut Grower Onboarding 4-Stage Lifecycle]\n\nNATCODEV verifies grower identity to protect the cooperative and its buyers from duplicate or fraudulent entries. You do not need every document at once, but you should understand what each one proves and how to submit a copy that passes review the first time.\n\n[CHECKLIST]Government photo ID (NIN card, permanent voter card, or driver's licence)\nLand document, survey plan, or confirmation letter from local cooperative leader\nClear daylight photo of farm entrance showing a recognizable permanent landmark\nEstimated count of mature bearing palms vs newly planted seedlings[/CHECKLIST]\n\n[WARNING]Never upload dark, cropped, or blurred photos of identity documents. Image clarity failures account for over 80% of rejected verification requests.[/WARNING]\n\nIf a document is rejected, do not resubmit the same image. Read the rejection reason, fix the issue (better lighting, clearer text, correct document type), and upload again. Verified documents stay valid across programs, so this effort pays off every time you join a new course, apply for inputs, or list produce on the marketplace.",
            ],
            [
                'title' => 'Wallet Basics and Getting Help',
                'summary' => 'Fund your wallet, read your balance, and reach the support desk the right way.',
                'duration' => 30,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Your NATCODEV wallet holds funds for input purchases, course fees, and marketplace settlements. Direct bank transfer with your dedicated virtual account is zero-fee and instant.[/TAKEAWAY]\n\nTo fund the wallet, open the wallet screen and choose a payment method. Card payments and bank transfer are supported through the platform's payment partners; bank transfer uses a reserved account number tied to your user ID, so always transfer from your own registered bank account and use the exact reference shown.\n\n[PROTIP]Your wallet balance consists of Available Funds (ready to withdraw or spend) and Held Funds (reserved in escrow for active orders or courses until verified).[/PROTIP]\n\nFor help, use the in-app support desk rather than calling a field agent directly. Describe the issue, attach a screenshot of the transaction or error, and quote the wallet reference. Support staff can trace payments in the system and escalate to the finance team when needed. Keep your phone handy for one-time passwords, and never share your password or OTP with anyone, including people claiming to be platform staff.",
            ],
        ],
        'materials' => [
            ['title' => 'Grower Profile Checklist', 'type' => 'checklist', 'material_url' => '../academy/download-course.php?course_id=1', 'notes' => 'One-page checklist: name, state/LGA, phone, email, farm size, stand count, documents.'],
            ['title' => 'Document Upload Guide', 'type' => 'guide', 'material_url' => '../academy/download-course.php?course_id=1', 'notes' => 'Accepted document types, photo quality standards, and common rejection reasons.'],
            ['title' => 'Wallet Quick Reference', 'type' => 'reference', 'material_url' => '../academy/download-course.php?course_id=1', 'notes' => 'Funding methods, reserved accounts, hold vs available balance, dispute references.'],
        ],
    ],

    2 => [
        'overview' => 'NATCODEV Registry Data Quality Basics teaches growers and field staff why clean records matter and how to keep phone numbers, emails, names, and locations accurate. The registry drives payments, certifications, and supply planning, so every learner leaves able to spot and fix the six most common data errors.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Why Clean Grower Records Matter',
                'summary' => 'The registry is the source of truth for payments, supply, and certification.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/natcodev-registry-data-quality-basics-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/registry-data-quality-en.mp3',
                    'pcm' => '../academy_uploads/audio/registry-data-quality-pcm.mp3',
                    'ha' => '../academy_uploads/audio/registry-data-quality-ha.mp3',
                    'yo' => '../academy_uploads/audio/registry-data-quality-yo.mp3',
                    'ig' => '../academy_uploads/audio/registry-data-quality-ig.mp3',
                ],
                'content' => "[TAKEAWAY]The registry is the source of truth for payments, inputs, and certification. One person, one record, kept current is the fundamental standard.[/TAKEAWAY]\n\nThe NATCODEV registry is not just a list of names. It is the source of truth that payments, input distribution, certification, and market matching all depend on. When a record is wrong, the error travels: a wrong phone number breaks wallet alerts, a wrong LGA misroutes field visits, and a duplicate profile splits a grower's history across two accounts.\n\n[STEPS]1. Review Core Standard: Confirm requirements and objectives.\n2. Apply Field Discipline: Follow approved procedure step by step.\n3. Verify Documentation: Keep verified logs and audit evidence.\n4. Complete Assessment: Test understanding and claim certificate.[/STEPS]\n\nConsider what happens at payment time. If a grower has two profiles, the cooperative may pay the wrong one, or hold both pending manual checks. The receiver waits, support gets a ticket, and trust in the program erodes. Most of these cases are preventable at data entry, which is why data quality is everyone's job, not just the administrator's.\n\n[PROTIP]Always verify your primary phone number and check that your State and LGA match your physical farm location before applying for inputs.[/PROTIP]\n\nClean data also unlocks services. Growers with verified phones and documents are eligible for input financing, certification courses, and marketplace verification badges. Buyers prefer verified profiles because they can trace produce back to a real farm. In short, the quality of your record directly controls the value the platform can deliver to you.\n\nThe golden rule: one person, one record, kept current. If you notice a duplicate or an error, report it through the support desk with evidence rather than creating a second account. Consolidation is quick when the evidence is clear, and it protects your transaction history, certificates, and wallet balance in one place.",
            ],
            [
                'title' => 'Phone, Email and Contact Discipline',
                'summary' => 'One reachable phone, one real email, and rules for changing them safely.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/natcodev-registry-data-quality-basics-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/registry-data-quality-en.mp3',
                    'pcm' => '../academy_uploads/audio/registry-data-quality-pcm.mp3',
                    'ha' => '../academy_uploads/audio/registry-data-quality-ha.mp3',
                    'yo' => '../academy_uploads/audio/registry-data-quality-yo.mp3',
                    'ig' => '../academy_uploads/audio/registry-data-quality-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:grower-onboarding-workflow|Registry Identity & Contact Verification Standard]\n\n[TAKEAWAY]The registry is the source of truth for payments, inputs, and certification. One person, one record, kept current is the fundamental standard.[/TAKEAWAY]\n\nContact fields are the most valuable and most fragile part of your record. The phone number is used for login, one-time passwords, wallet alerts, and field-agent contact. The email receives receipts, course certificates, and password resets. Keep both reachable and current.\n\n[WARNING]Do not create multiple accounts or guess LGA names. Duplicate accounts split payment records and delay field verification.[/WARNING]\n\nUse one phone number per profile. Shared family phones create confusion when OTPs arrive for the wrong person, and SIM swaps without an update lock growers out of their accounts. If you change your SIM or number, update the profile immediately from a device that can still receive the old number's messages, or visit a field agent with your ID for a verified change.\n\n[PROTIP]Always verify your primary phone number and check that your State and LGA match your physical farm location before applying for inputs.[/PROTIP]\n\nEmails should be real and checked at least weekly. Disposable addresses are blocked because certificate delivery and dispute trails depend on a durable inbox. If your email provider changes or you lose access, update it early; a lost email plus a lost phone is the hardest recovery case the support desk handles.\n\nNames and locations follow the same discipline. Use your legal name consistently, and pick your state and LGA from the dropdowns. Free-text locations cannot be aggregated for reporting and often hide typos that misroute assignments. When every contact field is disciplined, verification, payments, and communication all run without manual intervention.",
            ],
            [
                'title' => 'State and LGA Data Integrity',
                'summary' => 'How state/LGA values drive coverage, assignment, and reporting accuracy.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/natcodev-registry-data-quality-basics-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/registry-data-quality-en.mp3',
                    'pcm' => '../academy_uploads/audio/registry-data-quality-pcm.mp3',
                    'ha' => '../academy_uploads/audio/registry-data-quality-ha.mp3',
                    'yo' => '../academy_uploads/audio/registry-data-quality-yo.mp3',
                    'ig' => '../academy_uploads/audio/registry-data-quality-ig.mp3',
                ],
                'content' => "[TAKEAWAY]The registry is the source of truth for payments, inputs, and certification. One person, one record, kept current is the fundamental standard.[/TAKEAWAY]\n\nState and local government area (LGA) values are the geographic backbone of the registry. Field agents are assigned by LGA, coverage reports are aggregated by state, and input distribution plans are built from LGA-level grower counts. A single wrong dropdown choice can move your farm to the wrong coverage map.\n\n[CHECKLIST]Verify identity and account records\nMaintain clean operational logs\nFollow verified safety and quality standards\nSubmit timely completion evidence[/CHECKLIST]\n\nAlways select from the dropdown lists rather than typing. The lists are the official hierarchy used by every report, so a selection that looks similar but comes from free text will not aggregate correctly. If your LGA is genuinely missing from the list, that is a registry defect: report it to the support desk so the hierarchy can be corrected for everyone, instead of inventing a workaround.\n\n[PROTIP]Always verify your primary phone number and check that your State and LGA match your physical farm location before applying for inputs.[/PROTIP]\n\nField agents verify grower locations against GPS evidence during farm visits. If your registered LGA does not match your GPS coordinates, the visit may be flagged as out-of-coverage, and your verification can be delayed. When you relocate your farming activity to a new LGA, update the profile before the next scheduled visit.\n\nCoordinators use state-level dashboards to spot data quality issues, such as many growers in one LGA with unverified phones. Those signals drive training and cleanup campaigns. By keeping your own state and LGA accurate, you make the dashboards honest, and honest dashboards are what the cooperative uses to allocate resources fairly across states.",
            ],
        ],
        'materials' => [
            ['title' => 'Data Quality Self-Audit Sheet', 'type' => 'template', 'material_url' => '../academy/download-course.php?course_id=2', 'notes' => 'Check your name, phone, email, state, LGA, and documents against this sheet monthly.'],
            ['title' => 'Common Registry Errors', 'type' => 'reference', 'material_url' => '../academy/download-course.php?course_id=2', 'notes' => 'The six most common errors, their symptoms, and how to fix each one.'],
        ],
    ],

    3 => [
        'overview' => 'Farm Hand Safety and Field Task Orientation prepares farm hands for safe, accountable field work on NATCODEV-registered farms. It covers the main farm work categories, the standard safety checklist and personal protective equipment, and how task evidence and supervisor reporting keep every worker accounted for and paid correctly.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Understanding Farm Work Categories',
                'summary' => 'Planting, maintenance, harvest, and processing tasks and their risks.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/farm-hand-safety-and-field-task-orientation-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/farmhand-safety-en.mp3',
                    'pcm' => '../academy_uploads/audio/farmhand-safety-pcm.mp3',
                    'ha' => '../academy_uploads/audio/farmhand-safety-ha.mp3',
                    'yo' => '../academy_uploads/audio/farmhand-safety-yo.mp3',
                    'ig' => '../academy_uploads/audio/farmhand-safety-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Every field job starts with safety: confirm the task category, wear appropriate PPE, and use the buddy rule when working with sharp tools or chemicals.[/TAKEAWAY]\n\nField work on a coconut operation falls into a few broad categories, and each has its own tools, risks, and evidence expectations. Knowing the category of a task before you start it is the first safety habit, because it tells you what protection you need and what record must be kept afterwards.\n\n[STEPS]1. Review Core Standard: Confirm requirements and objectives.\n2. Apply Field Discipline: Follow approved procedure step by step.\n3. Verify Documentation: Keep verified logs and audit evidence.\n4. Complete Assessment: Test understanding and claim certificate.[/STEPS]\n\nPlanting and nursery work involves lifting seed nuts, digging, and carrying seedlings. The main risks are strains from lifting, cuts from tools, and slips on wet nursery soil. Maintenance work covers weeding, pruning, fertiliser application, and pest inspection; chemical handling is the dominant risk here, so gloves, boots, and careful mixing discipline matter. Harvest work means climbing or using poles and knives to bring down bunches, with falls and cuts as the chief hazards.\n\n[PROTIP]Always lift heavy loads using leg muscles rather than the lower back. Ask a coworker to assist with bags or seed bins exceeding 25kg.[/PROTIP]\n\nProcessing and loading work includes de-husking, sorting, and moving nuts to collection points. Heavy lifting and vehicle movement around collection areas are the main dangers. Each category also has a documentation side: plantings are logged against farm plots, harvests against stand counts, and inputs against usage records that later support the grower's production history.\n\nWhen a supervisor assigns a task, confirm the category, the location, and the expected duration before you begin. If the task description and what you find in the field do not match, stop and report back rather than improvising. Task records built from real categories are what allow the cooperative to verify work done, settle daily wages, and plan the next season's labour.",
            ],
            [
                'title' => 'Field Safety Checklist and PPE',
                'summary' => 'The standard pre-task checklist and the protective gear for each job.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/farm-hand-safety-and-field-task-orientation-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/farmhand-safety-en.mp3',
                    'pcm' => '../academy_uploads/audio/farmhand-safety-pcm.mp3',
                    'ha' => '../academy_uploads/audio/farmhand-safety-ha.mp3',
                    'yo' => '../academy_uploads/audio/farmhand-safety-yo.mp3',
                    'ig' => '../academy_uploads/audio/farmhand-safety-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:coconut-nursery-lifecycle|Field Operations & Personal Protective Equipment Protocol]\n\n[TAKEAWAY]Every field job starts with safety: confirm the task category, wear appropriate PPE, and use the buddy rule when working with sharp tools or chemicals.[/TAKEAWAY]\n\nEvery farm task starts with the same five-question checklist: What is the task? Where exactly will it happen? What tools and chemicals are needed? What protective equipment does it require? Who is my contact if something goes wrong? Answering these aloud with your supervisor takes two minutes and prevents most field incidents.\n\n[WARNING]Never work at height during rain or high wind. Inspect all ladders, cutting poles, and safety belts prior to every harvest climb.[/WARNING]\n\nPersonal protective equipment (PPE) follows the task category. For chemical work, wear chemical-resistant gloves, closed boots, long sleeves, and eye protection; mix outdoors or in ventilated areas, never measure chemicals by taste or smell, and store leftovers in labelled containers away from food and water. For cutting and climbing, use a hard hat where falling material is possible, cut-resistant gloves, and secure footwear; inspect knives and poles for damage before use.\n\n[PROTIP]Always lift heavy loads using leg muscles rather than the lower back. Ask a coworker to assist with bags or seed bins exceeding 25kg.[/PROTIP]\n\nHeat is a year-round hazard on coconut farms. Schedule heavy work for cooler hours, drink water at regular intervals, and use shade breaks. Never work alone in remote plots when using sharp tools or chemicals; the buddy rule is standard practice. Keep a basic first-aid kit at the plot entrance and know the nearest health post before you start.\n\nAfter the task, close the loop: clean and store PPE, wash hands and clothing, and report any incident, near-miss, or equipment damage to your supervisor the same day. Incidents reported early are fixable; incidents hidden become next season's serious injuries. The checklist only works if it is used every single time, not when the weather is bad or the deadline is tight.",
            ],
            [
                'title' => 'Task Reporting and Supervisor Assignment',
                'summary' => 'How task evidence, attendance, and reports connect work to pay.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/farm-hand-safety-and-field-task-orientation-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/farmhand-safety-en.mp3',
                    'pcm' => '../academy_uploads/audio/farmhand-safety-pcm.mp3',
                    'ha' => '../academy_uploads/audio/farmhand-safety-ha.mp3',
                    'yo' => '../academy_uploads/audio/farmhand-safety-yo.mp3',
                    'ig' => '../academy_uploads/audio/farmhand-safety-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Every field job starts with safety: confirm the task category, wear appropriate PPE, and use the buddy rule when working with sharp tools or chemicals.[/TAKEAWAY]\n\nTask reporting is how your work becomes verifiable. A supervisor assigns a task with a category, location, and expected duration; you complete it; and the record you leave behind is what the grower and the cooperative use to confirm attendance, quality, and payment. Understand this chain, because your pay depends on the record, not just the work.\n\n[CHECKLIST]Verify identity and account records\nMaintain clean operational logs\nFollow verified safety and quality standards\nSubmit timely completion evidence[/CHECKLIST]\n\nWhen you are assigned a task, confirm you can see it in your task list with the correct farm and plot. If a task is missing or misassigned, tell the supervisor before starting; work done on an unassigned plot is hard to evidence later. During the task, follow the agreed method: for example, plant at the marked spacing, or apply only the measured quantity of fertilizer. Deviations should be agreed with the supervisor first.\n\n[PROTIP]Always lift heavy loads using leg muscles rather than the lower back. Ask a coworker to assist with bags or seed bins exceeding 25kg.[/PROTIP]\n\nOn completion, submit the task evidence required for that category: a photo of the planted plot, the count of palms treated, or the quantity harvested. Photos should show the work clearly with a landmark that ties them to the plot. Add a short note if anything unusual happened, such as weather delays or a pest outbreak; these notes become valuable context for the grower's farm records.\n\nAttendance and task records feed the grower's production history and your work history. Growers use the history to plan harvests and apply for programs; workers use theirs to build a reputation that leads to more assignments and better pay. Report honestly, submit evidence the same day, and ask your supervisor to correct errors immediately while they are still easy to fix.",
            ],
        ],
        'materials' => [
            ['title' => 'Five-Question Safety Checklist', 'type' => 'checklist', 'material_url' => '../academy/download-course.php?course_id=3', 'notes' => 'Printable pre-task safety checklist for every field job.'],
            ['title' => 'PPE by Task Category', 'type' => 'reference', 'material_url' => '../academy/download-course.php?course_id=3', 'notes' => 'Required protective gear for planting, chemical, harvest, and processing work.'],
            ['title' => 'Task Evidence Guide', 'type' => 'guide', 'material_url' => '../academy/download-course.php?course_id=3', 'notes' => 'What photo and note evidence is expected for each work category.'],
        ],
    ],

    4 => [
        'overview' => 'Provider Registration and Accreditation Readiness is the starter course for input and service providers joining the NATCODEV marketplace. It covers completing your business profile, declaring coverage states and LGAs honestly, and preparing products and services for review so your accreditation can be approved faster.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Building Your Provider Profile',
                'summary' => 'The business profile fields reviewers check first, and how to make them strong.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/provider-registration-and-accreditation-readiness-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/provider-accreditation-en.mp3',
                    'pcm' => '../academy_uploads/audio/provider-accreditation-pcm.mp3',
                    'ha' => '../academy_uploads/audio/provider-accreditation-ha.mp3',
                    'yo' => '../academy_uploads/audio/provider-accreditation-yo.mp3',
                    'ig' => '../academy_uploads/audio/provider-accreditation-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Accreditation confirms business legitimacy, physical operating presence, and verified product listings across declared LGAs.[/TAKEAWAY]\n\nYour provider profile is your business identity on the platform. Reviewers, growers, and buyers all form their first impression from it, and the accreditation decision starts with the completeness and consistency of these fields. Treat profile setup like opening a new branch office: accurate, verifiable, and current.\n\n[STEPS]1. Review Core Standard: Confirm requirements and objectives.\n2. Apply Field Discipline: Follow approved procedure step by step.\n3. Verify Documentation: Keep verified logs and audit evidence.\n4. Complete Assessment: Test understanding and claim certificate.[/STEPS]\n\nBegin with the legal business name and registration details. If your business is registered with the Corporate Affairs Commission, use the exact registered name and number; if it is a sole proprietorship or family enterprise, use the trading name you use everywhere else, and keep it consistent with your bank account name. Inconsistencies between profile, documents, and bank details are the most common reason accreditation is delayed.\n\n[PROTIP]State realistic delivery lead times and exact operating LGAs in your storefront to maintain high customer review scores and repeat orders.[/PROTIP]\n\nAdd contact information that a customer can actually use: a phone that is answered during business hours, an email monitored by a person, and a physical address or base of operations. Providers with only a phone number and no location are flagged as higher risk because buyers cannot verify them. If you operate from a market stall or depot, describe it precisely, including the market name and unit.\n\nFinally, write a short service description in plain language: what you sell, which crops or farm stages it serves, and what makes your offering reliable. Avoid marketing claims that cannot be verified; reviewers verify claims, and unsupported promises (such as 'nationwide delivery' when you serve two states) work against you. A modest, accurate profile builds trust faster than a flashy, exaggerated one.",
            ],
            [
                'title' => 'Coverage States and LGAs',
                'summary' => 'Declare where you actually operate; coverage drives buyer matching and trust.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/provider-registration-and-accreditation-readiness-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/provider-accreditation-en.mp3',
                    'pcm' => '../academy_uploads/audio/provider-accreditation-pcm.mp3',
                    'ha' => '../academy_uploads/audio/provider-accreditation-ha.mp3',
                    'yo' => '../academy_uploads/audio/provider-accreditation-yo.mp3',
                    'ig' => '../academy_uploads/audio/provider-accreditation-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:wallet-settlement-flow|Provider Commercial Accreditation & Logistics Flow]\n\n[TAKEAWAY]Accreditation confirms business legitimacy, physical operating presence, and verified product listings across declared LGAs.[/TAKEAWAY]\n\nCoverage is the map of where you genuinely deliver products or services. The platform uses your declared states and LGAs to match you with nearby buyers, to route verification visits, and to calculate coverage statistics for the cooperative. Declaring coverage you cannot serve damages buyers and your own reputation.\n\n[WARNING]Listing counterfeit, diluted, or unregistered agricultural inputs results in permanent provider de-accreditation and forfeiture of wallet bond.[/WARNING]\n\nDeclare your real operating area first, then grow it. A provider honestly covering three LGAs is more valuable than one claiming thirty states with no stock in most of them. When a buyer in an LGA you do not serve finds you through a broad claim and you cannot fulfil the order, the resulting cancellation and complaint count against your reliability score.\n\n[PROTIP]State realistic delivery lead times and exact operating LGAs in your storefront to maintain high customer review scores and repeat orders.[/PROTIP]\n\nCoverage changes are normal: a new depot, a seasonal route, a partner warehouse. Update your coverage whenever it changes, and expect the platform to sample-check coverage during verification. Field staff may confirm that a claimed LGA has a real presence, so keep evidence such as depot photos, delivery records, or partner agreements.\n\nRemember that coverage interacts with logistics. If you serve remote LGAs, state your delivery lead times honestly in your service description. Buyers weigh lead time against need, and an honest two-week delivery beats a promised two-day delivery that quietly becomes two weeks. Accurate coverage plus honest lead times is the combination that earns repeat business.",
            ],
            [
                'title' => 'Product and Service Readiness',
                'summary' => 'Listing standards, stock discipline, and what reviewers verify.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/provider-registration-and-accreditation-readiness-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/provider-accreditation-en.mp3',
                    'pcm' => '../academy_uploads/audio/provider-accreditation-pcm.mp3',
                    'ha' => '../academy_uploads/audio/provider-accreditation-ha.mp3',
                    'yo' => '../academy_uploads/audio/provider-accreditation-yo.mp3',
                    'ig' => '../academy_uploads/audio/provider-accreditation-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Accreditation confirms business legitimacy, physical operating presence, and verified product listings across declared LGAs.[/TAKEAWAY]\n\nProducts and services are listed against categories so buyers can filter and compare. Each listing needs a clear title, the correct category, a unit of measure, a price, and a photo where applicable. Listings that skip fields or use vague titles such as 'good fertilizer' are returned for correction before they go live.\n\n[CHECKLIST]Verify identity and account records\nMaintain clean operational logs\nFollow verified safety and quality standards\nSubmit timely completion evidence[/CHECKLIST]\n\nUse precise, searchable titles: the product name, grade or strength, and unit. For inputs, include the formulation where known, because agronomists and experienced growers filter by it. Prices should be your real current prices; listing a bait price to attract inquiries and then negotiating upward is a policy violation that can suspend the listing. If input prices fluctuate, update the listing rather than hiding the change.\n\n[PROTIP]State realistic delivery lead times and exact operating LGAs in your storefront to maintain high customer review scores and repeat orders.[/PROTIP]\n\nStock discipline matters as much as listing quality. Mark items out of stock promptly when they run out, and restock with realistic dates. Buyers plan spray programs and planting schedules around availability, and an unexpectedly unavailable item can cost them a whole season's timing. Where you offer services (such as spraying, transport, or pruning), describe the scope, the area covered, and how booking works.\n\nReviewers verify a sample of listings against your coverage and registration documents. Keep invoices, supplier agreements, or product registration evidence where required, and be ready to upload them. A listing set that is complete, accurately priced, and honestly stocked is the fastest path through accreditation review.",
            ],
        ],
        'materials' => [
            ['title' => 'Provider Profile Checklist', 'type' => 'checklist', 'material_url' => '../academy/download-course.php?course_id=4', 'notes' => 'Fields reviewers check, with tips for legal name, contacts, and description.'],
            ['title' => 'Coverage Update Form', 'type' => 'template', 'material_url' => '../academy/download-course.php?course_id=4', 'notes' => 'Template for requesting coverage changes with evidence.'],
            ['title' => 'Listing Standards Quick Card', 'type' => 'reference', 'material_url' => '../academy/download-course.php?course_id=4', 'notes' => 'Title, category, unit, price, and photo standards for every listing.'],
        ],
    ],

    5 => [
        'overview' => 'Marketplace Buyer and Seller Starter Clinic orients new marketplace users to storefronts, listing quality, inquiry handling, and safe trade. It is the shared starting point for sellers opening their first storefront and buyers learning to discover verified supply, compare quotes, and purchase with confidence.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Storefront Basics for New Sellers',
                'summary' => 'Set up a storefront that buyers can find, trust, and transact with.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/marketplace-buyer-and-seller-starter-clinic-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/marketplace-clinic-en.mp3',
                    'pcm' => '../academy_uploads/audio/marketplace-clinic-pcm.mp3',
                    'ha' => '../academy_uploads/audio/marketplace-clinic-ha.mp3',
                    'yo' => '../academy_uploads/audio/marketplace-clinic-yo.mp3',
                    'ig' => '../academy_uploads/audio/marketplace-clinic-ig.mp3',
                ],
                'content' => "[TAKEAWAY]A reputable storefront combines clear product grades, transparent pricing, and fast response to inquiries within the platform.[/TAKEAWAY]\n\nYour storefront is your shop window on the NATCODEV marketplace. Buyers discover it through search, category browsing, and verification badges, so the first goal is simple: make it easy for a serious buyer to see what you sell, where you are, and how to buy.\n\n[STEPS]1. Review Core Standard: Confirm requirements and objectives.\n2. Apply Field Discipline: Follow approved procedure step by step.\n3. Verify Documentation: Keep verified logs and audit evidence.\n4. Complete Assessment: Test understanding and claim certificate.[/STEPS]\n\nStart with the storefront name and cover details. Use your registered business or farm name, and choose a category that matches your main produce or product. Buyers filter by category, so a wrong category hides you from the right audience even if your listing is excellent. Add your state and LGA, because many buyers search by origin, especially for fresh produce where distance affects freshness and cost.\n\n[PROTIP]Photograph actual produce in good natural daylight showing size, husking grade, and packaging before dispatching consignments.[/PROTIP]\n\nYour storefront description should answer three questions in plain language: what do you sell, how much can you supply, and how do you deliver? Capacity matters to buyers; a buyer planning a container load needs to know whether you can supply ten tonnes or only two. State your usual lead time and your delivery or pickup arrangement. Uncertainty about supply or logistics is the top reason buyers abandon a new seller.\n\nFinally, keep the storefront active. Respond to inquiries within a day, keep listings in stock or marked out, and ask satisfied buyers to leave feedback. Your reliability score builds from real transactions, and that score is what moves you up in buyer search results over time. A quiet, well-kept storefront with accurate information outperforms a flashy but inaccurate one.",
            ],
            [
                'title' => 'Listing Quality That Converts',
                'summary' => 'Titles, photos, grades, and pricing that win buyer confidence.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/marketplace-buyer-and-seller-starter-clinic-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/marketplace-clinic-en.mp3',
                    'pcm' => '../academy_uploads/audio/marketplace-clinic-pcm.mp3',
                    'ha' => '../academy_uploads/audio/marketplace-clinic-ha.mp3',
                    'yo' => '../academy_uploads/audio/marketplace-clinic-yo.mp3',
                    'ig' => '../academy_uploads/audio/marketplace-clinic-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:wallet-settlement-flow|Marketplace Escrow & Settlement Lifecycle]\n\n[TAKEAWAY]A reputable storefront combines clear product grades, transparent pricing, and fast response to inquiries within the platform.[/TAKEAWAY]\n\nA good listing does the selling before the buyer ever contacts you. Three elements do most of the work: a precise title, honest grades and quantities, and a price that reflects the real market.\n\n[WARNING]Never transact or accept payments outside the platform. Off-platform transactions strip buyer and seller dispute protections.[/WARNING]\n\nTitles should name the product, its grade or variety, and the unit. For coconut produce, include the form (fresh nut, husked, de-husked, copra) and the grade or size class where one exists. Buyers searching for 'de-husked coconut, medium, per 100' will not find a listing titled 'coconuts available'. Use the same vocabulary your buyers use, and check what competing listings call the same product.\n\n[PROTIP]Photograph actual produce in good natural daylight showing size, husking grade, and packaging before dispatching consignments.[/PROTIP]\n\nPhotos and descriptions must show reality. If a photo is available, take it at the farm or depot with good light; avoid stock images that misrepresent the product. Describe what is included, the packaging, and any defects honestly. Buyers accept seasonal variation when it is stated; they dispute it when it is discovered on delivery.\n\nPrice for the market, not for the best case. Check recent listings and prevailing prices for your grade, and remember that buyers compare multiple sellers before committing. A realistic price with a clear unit and honest supply capacity attracts qualified inquiries; an unrealistic price attracts time-wasters or, worse, no one at all. Update prices when the market moves, and never negotiate a buyer off a listed price you are unwilling to honour.",
            ],
            [
                'title' => 'Inquiries, Orders and Safe Trade',
                'summary' => 'Handle inquiries professionally and keep every step inside the platform.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/marketplace-buyer-and-seller-starter-clinic-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/marketplace-clinic-en.mp3',
                    'pcm' => '../academy_uploads/audio/marketplace-clinic-pcm.mp3',
                    'ha' => '../academy_uploads/audio/marketplace-clinic-ha.mp3',
                    'yo' => '../academy_uploads/audio/marketplace-clinic-yo.mp3',
                    'ig' => '../academy_uploads/audio/marketplace-clinic-ig.mp3',
                ],
                'content' => "[TAKEAWAY]A reputable storefront combines clear product grades, transparent pricing, and fast response to inquiries within the platform.[/TAKEAWAY]\n\nInquiries are the moment a listing becomes a deal. Respond within one business day, answer the buyer's specific questions (quantity, grade, price, delivery), and confirm anything you promise in writing inside the platform. The platform's message history is your evidence if a dispute arises later.\n\n[CHECKLIST]Verify identity and account records\nMaintain clean operational logs\nFollow verified safety and quality standards\nSubmit timely completion evidence[/CHECKLIST]\n\nWhen a buyer is serious, move to a platform order rather than an informal arrangement. Orders create a record with quantity, agreed price, and delivery terms, and they unlock the wallet flow for payment. Avoid taking payment outside the platform: off-platform payments remove your protection, make disputes nearly impossible to resolve, and violate marketplace policy. If a buyer insists on paying outside, decline politely and offer the platform checkout.\n\n[PROTIP]Photograph actual produce in good natural daylight showing size, husking grade, and packaging before dispatching consignments.[/PROTIP]\n\nOn fulfilment, deliver the grade and quantity you listed, and share tracking or pickup details promptly. For fresh produce, agree on quality at dispatch and keep photos of the consignment before handover. If something goes wrong, tell the buyer immediately and propose a remedy; early honesty prevents most disputes from escalating to formal resolution.\n\nAfter the sale, request feedback and keep your transaction records. Buyers rely on seller history, and every completed, well-handled order strengthens your profile. Treat the first ten orders as the foundation of your marketplace reputation: accurate listings, honest grading, platform payments, and fast communication.",
            ],
        ],
        'materials' => [
            ['title' => 'Storefront Setup Checklist', 'type' => 'checklist', 'material_url' => '../academy/download-course.php?course_id=5', 'notes' => 'Category, location, capacity, lead time, and contact basics for new sellers.'],
            ['title' => 'Listing Quality Rubric', 'type' => 'reference', 'material_url' => '../academy/download-course.php?course_id=5', 'notes' => 'Title, photo, grade, and pricing standards used by marketplace reviewers.'],
            ['title' => 'Safe Trade Rules', 'type' => 'guide', 'material_url' => '../academy/download-course.php?course_id=5', 'notes' => 'Why payments stay on-platform, and how to handle disputes step by step.'],
        ],
    ],
];
