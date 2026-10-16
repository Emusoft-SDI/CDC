<?php
declare(strict_types=1);

/**
 * Free course content, batch 2: grower programs, nursery, GAP,
 * records, wallet, procurement, and provider fundamentals.
 */

return [
    16 => [
        'overview' => 'Grower Onboarding Program is the practical starter path for newly registered growers. Complete your profile, establish your farm identity, prepare your documents, activate your wallet, and learn how to request help. Finish all lessons to receive your onboarding certificate.',
        'duration' => 80,
        'lessons' => [
            [
                'title' => 'Profile Completion and Farm Identity',
                'summary' => 'Build a complete, verifiable grower profile in one sitting.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Your grower profile is the single authoritative identity used across cooperative disbursements, input supplies, and buyer traceability. An incomplete record stalls all approvals.[/TAKEAWAY]\n\nThis lesson gets you from a new account to a complete, verifiable profile. Work through it in one sitting; a finished profile is the gate to everything else on the platform.\n\n[STEPS]1. Legal Identity Match: Enter your legal name, state, and LGA exactly as your documents show them.\n2. Farm Identity: Add your farm details: size, main crops, and approximate stand count.\n3. Contact Preferences: Set phone for OTPs and wallet alerts, email for receipts and certificates.\n4. Verification Signoff: Submit complete records for cooperative verification.[/STEPS]\n\n[PROTIP]Never type State or LGA names in free text. Reports, farm input trucks, and field-agent assignments are aggregated strictly by system-standard LGA codes.[/PROTIP]\n\nWhen the profile section shows complete, you are ready for document verification. The next lesson covers which documents to prepare and how to photograph them so they pass review the first time.",
            ],
            [
                'title' => 'Documents and Verification',
                'summary' => 'Prepare identity and farm documents that pass review.',
                'duration' => 25,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'grower-onboarding-workflow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:grower-onboarding-workflow|Coconut Grower Onboarding 4-Stage Lifecycle]\n\nVerification protects the cooperative and its programs from duplicates and fraud. You need a government-issued ID and something that ties you to your farm. For many smallholders, a cooperative leader's confirmation letter or a dated photo of the farm entrance with a landmark is accepted.\n\n[CHECKLIST]Government photo ID (NIN card, permanent voter card, or driver's licence)\nLand document, survey plan, or confirmation letter from local cooperative leader\nClear daylight photo of farm entrance showing a recognizable permanent landmark\nEstimated count of mature bearing palms vs newly planted seedlings[/CHECKLIST]\n\n[WARNING]Never upload dark, cropped, or blurred photos of identity documents. Image clarity failures account for over 80% of rejected verification requests.[/WARNING]\n\nAfter upload, watch the status column: pending, under review, verified, or rejected. Rejections always include a reason. Do not resubmit the same image; fix the cause first. Common fixes include better lighting, a clearer copy of the ID, or the correct document type.\n\nVerified documents are reused across programs, courses, and marketplace verification, so this effort pays back many times. Keep your originals safe and your digital copies in one folder for easy re-upload if the platform ever asks again.",
            ],
            [
                'title' => 'Wallet Activation and Support',
                'summary' => 'Fund your wallet, read your balance, and use the support desk.',
                'duration' => 30,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'wallet-settlement-flow',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:wallet-settlement-flow|NATCODEV Wallet & Escrow Settlement Flow]\n\n[TAKEAWAY]Your wallet is where program payments, input purchases, and marketplace settlements land. Virtual bank accounts provide instant zero-fee funding.[/TAKEAWAY]\n\nYour wallet is where program payments, input purchases, and marketplace settlements land. Activate it by confirming your phone number and funding it once, even with a small amount, so you know the flow works before you need it.\n\nFunding options are card and bank transfer. For transfer, the platform shows a reserved account number tied to your user ID. Always transfer from your own registered bank account and include the exact reference shown; unreferenced transfers are matched manually and can take up to one business day.\n\n[PROTIP]Learn to read your balance: Available Funds (ready for withdrawal or direct purchase) versus Held Balance (locked in escrow for orders until goods are delivered and confirmed).[/PROTIP]\n\nFor anything unclear, use the in-app support desk with a screenshot and the transaction reference. Support is available to growers at no cost, and using it early prevents small issues from becoming lost funds. This completes the onboarding path; finish the assessment to earn your certificate.",
            ],
        ],
        'materials' => [
            ['title' => 'Onboarding Completion Checklist', 'type' => 'checklist', 'notes' => 'Profile, documents, wallet, and support steps in order.'],
            ['title' => 'Verification Document Guide', 'type' => 'guide', 'notes' => 'Accepted documents, photo standards, rejection fixes.'],
        ],
    ],

    17 => [
        'overview' => 'Farm Hand Safety Program is a field-ready safety course for farm hands and the growers who supervise them. It covers farm work categories, the standard safety checklist and PPE, task evidence, and supervisor reporting so every worker stays safe, accounted for, and paid correctly.',
        'duration' => 80,
        'lessons' => [
            [
                'title' => 'Farm Work Categories and Hazards',
                'summary' => 'Know the category of each task before you start it.',
                'duration' => 25,
                'content' => "Safety starts with knowing what kind of work you are about to do. Coconut farm tasks fall into planting and nursery work, maintenance, harvest, and processing, and each category carries its own hazards and protective needs.\n\nPlanting and nursery work involves digging, lifting seed nuts, and carrying seedlings. Strains, cuts, and slips on wet soil are the main risks. Maintenance includes weeding, pruning, fertilising, and pest inspection; chemical handling dominates the risk here. Harvest means climbing or using poles and knives, with falls and cuts as the chief dangers. Processing and loading add heavy lifting and vehicle movement around collection points.\n\nBefore any task, ask which category it belongs to, what tools or chemicals it needs, and what protection is required. If the answer is unclear, ask your supervisor; starting a task you do not understand is the most common root of field injuries.\n\nCategory knowledge also protects your pay. Tasks are recorded against categories and plots, and the record is what confirms your work. Knowing the category means you can spot a misassignment early and report it before the day's work is logged wrongly.",
            ],
            [
                'title' => 'Safety Checklist and PPE',
                'summary' => 'The five-question checklist and the gear that goes with it.',
                'duration' => 25,
                'content' => "Run the five-question checklist before every task: What is the task? Where will it happen? What tools and chemicals are needed? What protective equipment is required? Who is my emergency contact? Two minutes of questions prevents most incidents.\n\nPersonal protective equipment follows the category. Chemical work: chemical-resistant gloves, closed boots, long sleeves, and eye protection; mix outdoors, never estimate chemicals by taste or smell, and store leftovers in labelled containers away from food and water. Cutting and climbing: secure footwear, cut-resistant gloves, and a hard hat where material can fall. Inspect tools and poles for damage before use.\n\nHeat is a constant hazard. Work heavy tasks in cooler hours, hydrate at regular intervals, and take shade breaks. Use the buddy rule for remote plots and sharp or chemical work. Know where the first-aid kit and nearest health post are before you begin, not after an injury.\n\nAfter the task, clean and store PPE, wash hands and clothing, and report any incident, near-miss, or damaged equipment the same day. The checklist only works if it is applied every time. A skipped checklist on an easy day is where the next serious injury is born.",
            ],
            [
                'title' => 'Task Evidence and Reporting',
                'summary' => 'Turn your work into verifiable records that confirm attendance and pay.',
                'duration' => 30,
                'content' => "Task reporting connects your work to your pay. The supervisor assigns a task with a category, location, and expected duration; you complete it; and the evidence you submit becomes the record that confirms attendance, quality, and payment.\n\nWhen a task is assigned, confirm it appears in your task list with the correct farm and plot. Work done on an unassigned plot is hard to evidence, so report missing or misassigned tasks before starting. Follow the agreed method, such as planting at the marked spacing or applying only the measured quantity of input. If conditions force a deviation, agree it with the supervisor first.\n\nOn completion, submit the evidence the category requires: a photo of the planted plot, the count of palms treated, or the quantity harvested. Photos should clearly show the work and include a landmark tying them to the plot. Add a short note for anything unusual, such as weather delays or pest outbreaks; these notes become valuable context in the grower's farm records.\n\nSubmit evidence the same day. Attendance and task records feed the grower's production history and your work history. Growers use theirs to plan harvests and apply for programs; you use yours to build a reputation that brings more assignments and better pay. Report honestly and ask for corrections immediately while they are easy to fix.",
            ],
        ],
        'materials' => [
            ['title' => 'Five-Question Safety Checklist', 'type' => 'checklist', 'notes' => 'Printable pre-task safety checklist for every field job.'],
            ['title' => 'PPE by Task Category', 'type' => 'reference', 'notes' => 'Required protective gear for planting, chemical, harvest, and processing work.'],
            ['title' => 'Task Evidence Guide', 'type' => 'guide', 'notes' => 'Photo and note evidence expected for each work category.'],
        ],
    ],

    136 => [
        'overview' => 'Growers Onboarding is a practical starter course for registered growers covering profile completion, farm identity, documents, wallet activation, and support. It is the fastest path from a new account to a verified, wallet-ready grower profile.',
        'duration' => 80,
        'lessons' => [
            [
                'title' => 'Your Profile and Farm Identity',
                'summary' => 'Complete the profile fields that verification and programs depend on.',
                'duration' => 25,
                'content' => "This lesson takes you from a fresh account to a complete profile. Work through it in one session; a complete profile unlocks courses, input programs, and marketplace participation.\n\nUse your legal name as shown on your identification, and select your state and LGA from the dropdowns. Free-text locations break reporting and can misroute your field agent. Enter your farm size, main crops, and stand count honestly; estimates are fine at onboarding, but update them after each harvest.\n\nSet a phone number you will keep, because OTPs and wallet alerts go there. Add an email for receipts and certificates. Choose a unique password, and never share it with anyone, including people who claim to be platform staff; staff will never ask for your password or OTP.\n\nIf you suspect a duplicate account exists, do not create another. Contact the support desk with your ID evidence so the records can be merged. One person, one record, kept current is the rule that keeps payments and programs working.",
            ],
            [
                'title' => 'Documents That Prove Your Farm',
                'summary' => 'Which documents to prepare and how to photograph them.',
                'duration' => 25,
                'content' => "Verification requires proof of identity and proof of farm. Identity: a government-issued ID such as a national ID, voter card, or driver's licence. Farm proof: a land document where available, or a confirmation letter from your cooperative leader, or a dated photo of your farm entrance with a visible landmark.\n\nQuality decides speed. Photograph documents in daylight, flat, with all corners visible. Farm photos should include a landmark that ties the image to your registered location. Blurry, cropped, or shadowed images are the most common rejection cause.\n\nUpload each document once. Duplicate uploads are flagged and slow the reviewer. After upload, watch the status: pending, under review, verified, or rejected with a reason. If rejected, fix the cause before resubmitting; the same bad image will be rejected again.\n\nVerified documents stay valid across programs and courses. Keep your originals safe and your digital copies in a single folder so re-upload, if ever requested, takes minutes rather than a field trip.",
            ],
            [
                'title' => 'Wallet Activation and the Support Desk',
                'summary' => 'Fund your wallet and learn where to get help.',
                'duration' => 30,
                'content' => "Your wallet holds funds for inputs, course fees, and marketplace settlements. Activate it by confirming your phone and making a small test payment so you know the flow works before you need it for something time-sensitive.\n\nFund by card or bank transfer. Bank transfers use a reserved account number tied to your user ID; always transfer from your own registered account with the exact reference shown. Unreferenced transfers are matched manually and can take up to one business day, so the reference is not optional.\n\nRead your balance as two numbers: available and held. Held amounts are reserved for pending transactions and release automatically when the transaction completes or is cancelled. Every movement shows a reference and timestamp; quote it in any dispute.\n\nFor help, use the in-app support desk with a screenshot and the transaction reference. Support staff can trace payments and escalate to finance when needed. Early, well-documented requests resolve fastest, so report problems the same day you spot them.",
            ],
        ],
        'materials' => [
            ['title' => 'Onboarding Completion Checklist', 'type' => 'checklist', 'notes' => 'Profile, documents, wallet, and support steps in order.'],
            ['title' => 'Verification Document Guide', 'type' => 'guide', 'notes' => 'Accepted documents, photo standards, and rejection fixes.'],
        ],
    ],

    137 => [
        'overview' => 'Coconut Nursery Masterclass is a hands-on course for nursery operators and growers establishing their own nurseries. It covers seed nut selection, germination techniques, and nursery care through the first six months, with emphasis on quality control and record keeping.',
        'duration' => 120,
        'lessons' => [
            [
                'title' => 'Seed Selection',
                'summary' => 'Criteria for selecting mature parent palms and seed nuts.',
                'duration' => 40,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:coconut-nursery-lifecycle|Coconut Nursery 5-Stage Propagation Lifecycle]\n\n[TAKEAWAY]A nursery is only as good as the seed nuts it starts with. Rigorous parent palm selection and float testing eliminate over 80% of defective seedlings before sowing.[/TAKEAWAY]\n\nA nursery is only as good as the seed nuts it starts with. Selection happens at two levels: choosing the parent palms that will bear the nuts, and choosing the individual nuts that will become seedlings.\n\n[STEPS]1. Elite Mother Palm Auditing: Select parent palms that are proven performers: tall, healthy, regular-bearing, and at least ten years into production, with a record of good yields.\n2. Maturity Curing: Harvest seed nuts at full brown husk maturity and cure in shade for 2-4 weeks to stabilize moisture.\n3. Saline / Fresh Float Testing: Discard buoyant floaters with hollow or damaged endosperms; only plant dense sinkers.\n4. Size Sorting & Bed Tagging: Grade nuts into uniform lots and tag with source mother palm registration IDs.[/STEPS]\n\n[PROTIP]Where the cooperative maintains a register of elite or certified palms, source from it; documented parentage is the first quality signal a buyer of seedlings will ask for.[/PROTIP]\n\nFloat-test the nuts before sowing: discard floaters, which usually have hollow or damaged kernels, and keep the sinkers. Grade nuts by size so the nursery bed is uniform, and record the source palm, harvest date, and grade for each batch. These records let you trace any batch that underperforms and adjust your source next season.",
            ],
            [
                'title' => 'Germination Techniques',
                'summary' => 'Optimal soil media, moisture, and spacing for even germination.',
                'duration' => 40,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Coconut nuts germinate best in warm, moist, well-drained media with steady shade. The operator's job is to keep those conditions uniform across the entire bed.[/TAKEAWAY]\n\nGermination is where nurseries either build or lose their reputation. Coconut nuts germinate best in warm, moist, well-drained media with steady shade, and the operator's job is to keep those conditions uniform across the whole bed.\n\n[CHECKLIST]Coarse washed river sand (60%) blended with aged organic matter (40%)\nPolybag drainage holes cleared on all four base quadrants\nHorizontal nut placement with eyes angled sideways to avoid sprout rot\nOverhead 50-60% woven shade netting secured against wind[/CHECKLIST]\n\n[WARNING]Never sow seed nuts vertically with the sprout eye pointing straight down or fully buried in clay soil; stagnant pooling rots the soft embryo in days.[/WARNING]\n\nSpacing matters more than it looks. Crowded nuts compete for moisture and make transplanting risky; allow roughly the width of two nuts between nuts, and mark rows for easy counting and later transplanting. Water to keep the media moist but never waterlogged, and adjust for weather: more in hot dry spells, less during rains.",
            ],
            [
                'title' => 'Nursery Care',
                'summary' => 'Routine monitoring, watering, shade, and disease prevention.',
                'duration' => 40,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'coconut-nursery-lifecycle',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[TAKEAWAY]Rigorous 10% to 15% culling of runts, stunted shoots, and spiral-leafed seedlings at month 5 protects the plantation from decades of poor yields.[/TAKEAWAY]\n\nOnce germination is underway, nursery care becomes a daily discipline. Seedlings are most vulnerable in the first weeks, and small lapses in watering, shade, or pest control show up as uneven growth that a buyer will notice.\n\n[PROTIP]Hardening-off protocol: Remove shade netting 4 weeks prior to field transplanting so seedlings acclimate to full tropical sun without wilting shock.[/PROTIP]\n\n[WARNING]Weed strictly by hand around nursery polybags. Drift from systemic herbicides like glyphosate will permanently stunt or kill young coconut shoots.[/WARNING]\n\nKeep a nursery log: germination counts, watering during dry spells, pest sightings, and treatments applied. The log is your quality evidence when selling seedlings, and it is the memory of the nursery; without it, you repeat last season's mistakes. Healthy, uniform, well-documented seedlings are what separate a masterclass nursery from an ordinary one.",
            ],
        ],
        'materials' => [
            ['title' => 'Seed Nut Selection Card', 'type' => 'reference', 'notes' => 'Maturity, float test, grading, and parent palm criteria.'],
            ['title' => 'Germination Log Template', 'type' => 'template', 'notes' => 'Batch records: source, sowing date, germination counts.'],
            ['title' => 'Nursery Daily Checklist', 'type' => 'checklist', 'notes' => 'Watering, shade, weeding, and pest checks for the first six months.'],
        ],
    ],

    138 => [
        'overview' => 'Coconut GAP Fundamentals introduces Good Agricultural Practices for coconut farming: soil and water management, planting standards, integrated pest management, harvest and post-harvest handling, and the record keeping that makes every practice verifiable.',
        'duration' => 120,
        'lessons' => [
            [
                'title' => 'Soil, Water and Planting Standards',
                'summary' => 'Site preparation, spacing, and water management that set up yield.',
                'duration' => 40,
                'content' => "Good agricultural practice starts before the first nut is planted. Site selection, soil preparation, and water management determine whether a coconut plantation thrives for decades or struggles from day one.\n\nCoconuts grow in a range of soils but do best in well-drained, deep soils with good organic content. Avoid low-lying areas where water stands after rain; waterlogging rots roots and invites disease. Test your soil if you can, and correct severe acidity or nutrient deficiency before planting, because young palms establish slowly in poor ground.\n\nStandard spacing allows light, air, and machinery between palms. A common layout is about 7.5 to 8 metres between palms, adjusted for variety and intercropping plans. Plant at the start of the rains so the first critical months are watered naturally, and water regularly in the first dry season until roots are established.\n\nMulch around each palm to hold moisture, suppress weeds, and feed soil life, and keep the mulch a short distance from the trunk to prevent rot. Intercrop with short-cycle crops in the early years to earn income while palms mature, but keep crops away from the palm basin so they do not compete for water or harbour pests.",
            ],
            [
                'title' => 'Integrated Pest and Disease Management',
                'summary' => 'Scout first, then control with the least harmful method.',
                'duration' => 40,
                'video_url' => '../academy_uploads/videos/coconut-grower-digital-onboarding-one-minute-brief.html',
                'infographic_url' => 'pest-disease-diagnostics',
                'audio_tracks' => [
                    'en' => '../academy_uploads/audio/grower-onboarding-en.mp3',
                    'pcm' => '../academy_uploads/audio/grower-onboarding-pcm.mp3',
                    'ha' => '../academy_uploads/audio/grower-onboarding-ha.mp3',
                    'yo' => '../academy_uploads/audio/grower-onboarding-yo.mp3',
                    'ig' => '../academy_uploads/audio/grower-onboarding-ig.mp3',
                ],
                'content' => "[INFOGRAPHIC:pest-disease-diagnostics|Coconut Pest & Pathogen Field Diagnostic Guide]\n\n[TAKEAWAY]Integrated Pest Management (IPM) prioritizes frequent crown scouting and biological hygiene over indiscriminate chemical spraying. Never spray on a fixed calendar schedule.[/TAKEAWAY]\n\nIntegrated pest management (IPM) means you scout, identify, and control pests and diseases with the least harmful effective method, not with a calendar spray schedule. The first rule of IPM is observation: walk your palms regularly and look at leaves, nuts, and the crown.\n\n[CHECKLIST]Inspect spear leaves for V-shaped scissor cuts caused by adult Rhinoceros Beetles\nCheck young nut buttons for triangular scarred patches caused by Aceria Mites\nExamine lower fronds for premature yellowing, collar collapse, and premature nut fall\nAudit dead standing logs and compost piles which serve as primary breeding habitats[/CHECKLIST]\n\n[WARNING]Lethal Yellowing is a phytoplasma pathogen spread by plant-hopper vectors. Palms displaying spear leaf necrosis must be reported immediately and quarantined to prevent epidemic grove loss.[/WARNING]\n\n[PROTIP]Biological control works: Maintain pheromone traps for Rhinoceros beetles (Oryctes monoceros) and treat breeding mulch with Metarhizium anisopliae green muscardine fungal spores.[/PROTIP]\n\nRecord every pest sighting and treatment: date, pest, product, rate, and weather. Records reveal patterns, such as a beetle surge after certain weather, and they are the evidence buyers and certification programs ask for when they verify your GAP claims.",
            ],
            [
                'title' => 'Harvest and Post-Harvest Handling',
                'summary' => 'Picking at maturity, safe handling, and storage that protect quality.',
                'duration' => 40,
                'content' => "Harvest timing and handling decide the price your nuts earn. A coconut is ready when it is fully mature for its intended use: drinking nuts are harvested younger, while processing and planting nuts need full maturity with a thick brown husk.\n\nHarvest by trained pickers using proper poles or climbing technique; never shake palms violently or throw down bunches, because damaged nuts bruise, crack, and spoil quickly. Drop-nuts left on the ground can be collected for processing but should never be mixed with fresh harvested nuts, as their quality is lower and they can carry contamination.\n\nAfter harvest, handle nuts gently. De-husk promptly if the market requires it, and sort by size and quality: remove cracked, damaged, or diseased nuts before transport. Keep nuts dry and shaded; moisture and heat accelerate mould and rot, and mould can produce toxins that make nuts unsafe and unsellable.\n\nStore nuts off the ground on racks or pallets in a ventilated, shaded area. For longer storage or export, follow the buyer's specific handling and fumigation requirements and keep the documentation. Good post-harvest handling preserves the premium that careful farming earned, and it is the part of the chain where quality is most often lost.",
            ],
        ],
        'materials' => [
            ['title' => 'GAP Field Guide', 'type' => 'guide', 'notes' => 'Soil, spacing, water, IPM, and harvest practices in one reference.'],
            ['title' => 'Pest and Disease Identification Card', 'type' => 'reference', 'notes' => 'Photo-free descriptions of key pests, signs, and first actions.'],
            ['title' => 'Harvest Handling Checklist', 'type' => 'checklist', 'notes' => 'Maturity, picking, sorting, drying, and storage steps.'],
        ],
    ],

    147 => [
        'overview' => 'Farm Records and Yield Tracking teaches professional record keeping for coconut farms: profile data, stand counts, yield logs, input usage, and the simple habits that turn scattered notes into a farm history the cooperative, lenders, and programs can trust.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Farm Profile Data',
                'summary' => 'The core data every farm record starts from.',
                'duration' => 25,
                'content' => "Every useful farm record starts from an accurate farm profile. The profile is the fixed reference point: location, area, varieties, and stand counts. If the profile is wrong, every record built on it is wrong too.\n\nRecord your farm's state and LGA from the official lists, the total area in hectares, and the number of palms by age class if you can. Age classes matter because young palms bear little or nothing while mature palms carry the harvest. Note the main variety or varieties; tall, dwarf, and hybrid palms behave differently and are managed differently.\n\nKeep a simple map or sketch of your plots, even hand-drawn, showing boundaries, the location of each block, and the variety in each. When a field agent visits with GPS evidence, your sketch helps you confirm the visit matches the right plot, and it helps you plan input delivery and harvest logistics.\n\nUpdate the profile when things change: new plantings, removed palms, a change of variety, or a change of address for the farm office. A profile updated once a season is the difference between a record that works and a record that quietly misleads everyone who relies on it.",
            ],
            [
                'title' => 'Yield Logs and Stand Counts',
                'summary' => 'Count your palms, log every harvest, and read the trends.',
                'duration' => 25,
                'content' => "A yield log is the financial memory of your farm. It answers the questions that matter: how many nuts did each block produce, when, and how does this season compare to last? Without a log, those answers are guesses.\n\nStart with a stand count: walk each block and count palms, noting missing or dead palms separately. Stand counts reveal losses from storms, disease, or theft that would otherwise hide in average yield figures. Re-count at least once a year, after the harvest season, and record the date.\n\nAt each harvest, log the date, the block, the number of bunches or nuts, and the weight if you can weigh. Weighing even a sample lets you estimate average nut weight and track whether yields are rising or falling. Note the weather and any inputs applied, because yield is a response to care and climate.\n\nRead your log each season: which blocks outperform, which lag, and whether a change you made (new input, better spacing, pest control) shows up in the numbers. Records that are read are records that improve your farming; records that are written and forgotten only collect dust.",
            ],
            [
                'title' => 'Input Usage and Compliance Records',
                'summary' => 'Log inputs, keep receipts, and make every application traceable.',
                'duration' => 25,
                'content' => "Input records protect your farm's market access and your wallet. Every application of fertilizer, pesticide, or organic treatment should be logged with the date, the product, the quantity, the block treated, and the person who applied it.\n\nWhy so detailed? Because buyers and certification programs increasingly ask for proof that inputs were used responsibly. A log shows you applied the labelled rate, observed the pre-harvest interval, and can trace any batch of nuts back to the treatments it received. That traceability is what turns an ordinary farm into a verified supplier.\n\nKeep receipts and product labels in one place, physical or digital, and match them to the log entries. If a product is recalled or a residue question arises, the log plus the receipt is your evidence that you used a compliant product correctly. Without it, you carry the burden of proof with nothing to show.\n\nSet a weekly habit: ten minutes every Friday to enter the week's applications and purchases from memory and receipts, while they are still fresh. A log kept current takes minutes; a log rebuilt at the end of the season takes hours and is full of errors.",
            ],
        ],
        'materials' => [
            ['title' => 'Farm Profile Sheet', 'type' => 'template', 'notes' => 'Location, area, varieties, age classes, and plot sketch.'],
            ['title' => 'Yield Log Template', 'type' => 'template', 'notes' => 'Per-harvest log: date, block, count, weight, weather, notes.'],
            ['title' => 'Input Application Log', 'type' => 'template', 'notes' => 'Date, product, quantity, block, applicator, and receipt references.'],
        ],
    ],

    148 => [
        'overview' => 'Wallet, Payments and Refund Readiness prepares growers and learners for the platform\'s money flow: funding the wallet, completing Monnify and card checkouts, understanding reserved accounts and holds, and following the refund process step by step.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Funding the Wallet',
                'summary' => 'Cards, bank transfer, and the reserved account flow.',
                'duration' => 25,
                'content' => "The wallet is the platform's settlement account for your transactions. Learn to fund it correctly once, and every later payment, course fee, or input purchase becomes routine.\n\nCard funding is instant: choose wallet funding, enter the amount, and complete the card checkout with your bank's one-time password. The balance should appear within a minute; if it does not, note the transaction reference rather than retrying immediately, because duplicate attempts can create multiple pending holds.\n\nBank transfer funding uses a reserved account number that belongs to your user ID. Transfer only from your own registered bank account, and always include the exact reference the platform shows. Transfers without a matching reference cannot be matched automatically; they queue for manual matching and can take up to one business day.\n\nAfter any funding, check the transactions list: a completed entry shows the amount, reference, and timestamp. If the entry shows pending, wait; if it shows failed, the funds were not taken and you can retry. Screenshot the confirmation, especially for transfers, until you trust the flow.",
            ],
            [
                'title' => 'Checkout, Holds and Balance',
                'summary' => 'How Monnify checkout works and what holds mean for your balance.',
                'duration' => 25,
                'content' => "When you buy a course, input, or marketplace order, the platform moves money from your wallet or through the Monnify checkout. Understanding holds prevents the most common payment worry: a balance that looks smaller than expected.\n\nYour wallet balance has two parts: available and held. When you start a checkout, the amount is held; it is reserved for that transaction and cannot be spent elsewhere. If the transaction completes, the hold becomes the payment. If it is cancelled, times out, or fails, the hold is released back to available automatically.\n\nHolds explain most \"missing money\" reports. Before contacting support, open the transactions list and look for pending or held entries; a held amount is still yours, just reserved. Multiple pending holds from repeated checkout attempts are the usual cause of a temporarily shrinking balance.\n\nFor marketplace purchases, the flow protects both sides: your payment is held until you confirm the order arrived as described, and only then is it released to the seller. Never release or confirm an order before you have received and checked the goods; that confirmation is the trigger that pays the seller.",
            ],
            [
                'title' => 'Refunds and Dispute Readiness',
                'summary' => 'When and how to request a refund, and what evidence to keep.',
                'duration' => 25,
                'content' => "Refunds are rare when transactions are handled correctly, but you should know the path before you need it. The refund process protects you when a course cannot be delivered, an input never arrives, or a marketplace order arrives wrong.\n\nStart in the platform where the transaction happened: the order or course screen has a request-refund or report-issue action. Describe the problem factually, quote the transaction reference, and attach evidence: photos of damaged or wrong goods, a screenshot of the failed checkout, or the seller's messages. Evidence, not emotion, is what moves a refund forward.\n\nRefunds return to the original payment method. Wallet payments refund to the wallet; card payments refund to the card and can take several business days to appear, depending on your bank. A refund that is approved but not yet visible is in processing; the transactions list shows its status.\n\nIf the seller or provider does not respond, escalate through the support desk. Support can mediate, and for marketplace orders the held payment gives the platform leverage to resolve fairly. Keep every message and receipt until the refund is fully settled; disputes are won on documentation, not on details remembered later.",
            ],
        ],
        'materials' => [
            ['title' => 'Wallet Funding Quick Card', 'type' => 'reference', 'notes' => 'Card and transfer steps, references, and pending-state meanings.'],
            ['title' => 'Holds and Balances Explainer', 'type' => 'guide', 'notes' => 'Available vs held, checkout holds, and release timing.'],
            ['title' => 'Refund Request Template', 'type' => 'template', 'notes' => 'What to include: reference, description, evidence, and escalation path.'],
        ],
    ],

    152 => [
        'overview' => 'Buyer Procurement and Verified Supply teaches buyers how to discover verified supply, run a disciplined request-for-quote process, place orders with evidence, and evaluate suppliers so every purchase is traceable and defensible.',
        'duration' => 75,
        'lessons' => [
            [
                'title' => 'Discovering Verified Supply',
                'summary' => 'Search, filters, and verification badges that signal real suppliers.',
                'duration' => 25,
                'content' => "The marketplace connects buyers with verified growers, processors, and providers. Discovering the right supply starts with search and filters, but the real skill is reading the signals that separate a reliable supplier from an unproven one.\n\nSearch by product and grade, then filter by state and LGA to keep logistics realistic; fresh produce loses value with distance. Sort by reliability score and read the supplier's profile: business details, coverage, listing quality, and transaction history. A verified badge means the platform has checked the supplier's identity and documents; it is not a guarantee, but it is a strong first filter.\n\nCheck the supplier's listings for the specifics you need: grade, form, unit, and stated capacity. A supplier who lists precise grades and realistic capacity is easier to transact with than one with vague listings. Contact them with a specific inquiry; the speed and quality of the reply is itself a signal.\n\nFor recurring needs, build a shortlist of two or three suppliers per region. Competition between shortlisted suppliers keeps quotes honest, and a backup protects you when your primary supplier has an off-season or a logistics failure.",
            ],
            [
                'title' => 'RFQ Discipline',
                'summary' => 'Write quotes requests that get comparable, honest answers.',
                'duration' => 25,
                'content' => "A request for quote (RFQ) is only as good as its specificity. Buyers who write vague requests get vague quotes and end up comparing apples to oranges. Write the RFQ so every supplier quotes the same thing.\n\nState the product, grade or variety, form (fresh, husked, de-husked, copra), unit of measure, quantity, and the delivery location or pickup arrangement. Give a delivery window, because a quote for immediate delivery differs from one for next month. Ask for the price per unit, the minimum order, and the lead time, in that order.\n\nSend the RFQ to several suppliers and log their replies in one table: price, lead time, minimum, and any conditions. Compare like with like; a low headline price with hidden loading costs or a distant depot is not cheap. Ask suppliers to confirm stock for your quantity, because quoted price without confirmed stock is just an intention.\n\nKeep every quote and reply in the platform's message history. The history is your audit trail for why you chose a supplier, and it protects you if a dispute arises later about what was promised. Disciplined RFQs are the cheapest procurement tool you have.",
            ],
            [
                'title' => 'Orders, Evidence and Supplier Evaluation',
                'summary' => 'Place orders on-platform, keep evidence, and score suppliers.',
                'duration' => 25,
                'content' => "Place every order on the platform rather than informally. An on-platform order records the quantity, agreed price, and terms, and it routes payment through the wallet flow where funds are held until you confirm receipt. That hold is your protection on every purchase.\n\nAt delivery, inspect before confirming: check the grade, quantity, and condition against the order. Photograph the consignment at handover, especially for fresh produce, and note any shortfall or damage on the delivery record. Confirm the order only when the goods match; confirmation releases the payment to the seller.\n\nIf the delivery is short, damaged, or the wrong grade, do not confirm. Raise it immediately in the platform with photos and the order reference, and follow the dispute process. Early reporting with evidence resolves most cases quickly; confirmed payments without inspection are the hardest to recover.\n\nAfter each order, score the supplier honestly in your own records: price, lead time, quality match, communication, and problem handling. Over time this evaluation builds your preferred-supplier list and gives you the data to negotiate better terms with the suppliers who consistently deliver.",
            ],
        ],
        'materials' => [
            ['title' => 'Verified Supply Search Guide', 'type' => 'guide', 'notes' => 'Filters, badges, and profile signals for finding reliable suppliers.'],
            ['title' => 'RFQ Template', 'type' => 'template', 'notes' => 'Product, grade, form, unit, quantity, window, and required quote fields.'],
            ['title' => 'Supplier Scorecard', 'type' => 'template', 'notes' => 'Price, lead time, quality, communication, and problem handling.'],
        ],
    ],

    203 => [
        'overview' => 'Input & Service Provider Fundamentals is the readiness course for input and service providers. It covers accreditation evidence, coverage declarations, product and service preparation, and the compliance basics that keep your listings live and your accreditation in good standing.',
        'duration' => 90,
        'lessons' => [
            [
                'title' => 'Accreditation Evidence',
                'summary' => 'The documents that prove your business is real and reachable.',
                'duration' => 30,
                'content' => "Accreditation is the platform's way of telling buyers that a provider is real, reachable, and accountable. The evidence you upload is what reviewers check, so prepare it deliberately rather than as an afterthought.\n\nCore evidence is your business registration or a recognised equivalent, your tax identifier where applicable, and a contact profile that works: a phone answered during business hours, a monitored email, and a physical base of operations such as a depot, stall, or office. Sole proprietors can use their trading name and ID; the key is consistency between the profile, the documents, and your bank account.\n\nFor input providers, add product evidence: supplier agreements, invoices, or product registration documents where the product category requires them. For service providers, describe the service scope, the equipment or qualifications behind it, and any certifications your team holds. Reviewers verify a sample of listings against this evidence, so keep it current and accessible.\n\nUpload clear scans or photos, one document per upload, and name files meaningfully. After submission, watch the accreditation status and respond quickly to any request for clarification. Providers who answer clarification requests within a day are accredited noticeably faster than those who let them sit.",
            ],
            [
                'title' => 'Coverage Declarations',
                'summary' => 'Declare the states and LGAs you truly serve, and update them.',
                'duration' => 30,
                'content' => "Your coverage declaration is the map buyers use to find you. It drives search matching, verification routing, and the cooperative's coverage statistics. The golden rule is to declare only what you genuinely serve, and to update it whenever reality changes.\n\nStart with your true operating area, even if it is small. A provider honestly covering two states with real stock and delivery is more valuable than one claiming twenty states with empty shelves in most of them. Over-declaration leads to unfulfilled orders, cancellations, and a falling reliability score, which hurts your visibility more than a modest coverage ever could.\n\nWhen you expand, add the new areas deliberately: confirm stock, delivery routes, and lead times before declaring. When you contract, remove areas promptly; an undeclared area that still shows live misleads buyers and damages trust. Expect the platform to sample-check coverage during verification, so keep evidence such as depot photos, delivery records, or partner agreements.\n\nRemember that coverage interacts with logistics honesty. If you serve remote LGAs, state realistic lead times in your listings. Buyers weigh delivery time against need, and an honest two-week delivery beats a promised two-day delivery that quietly becomes two weeks.",
            ],
            [
                'title' => 'Product, Service and Compliance Basics',
                'summary' => 'Listing standards, stock discipline, and staying in good standing.',
                'duration' => 30,
                'content' => "Listings are your storefront. Each needs a precise title, the correct category, a unit of measure, a real price, and a photo where applicable. Vague listings such as 'good fertilizer' or 'cheap service' are returned for correction before they go live, so invest the ten extra minutes in precision.\n\nFor inputs, include the formulation or grade where known, because experienced growers and agronomists filter by it. Price at your real current market; bait pricing followed by negotiation is a policy violation that can suspend the listing. If prices fluctuate, update the listing rather than hiding the change behind a conversation.\n\nStock discipline keeps buyers planning around you. Mark items out of stock promptly, restock with realistic dates, and never oversell what you cannot deliver. For services, describe the scope, the coverage, the booking process, and the lead time. A service listing that answers those four things converts far better than one that does not.\n\nCompliance is ongoing, not one-time. Keep your accreditation evidence current, respond to platform requests, and honour the marketplace policies on payments and disputes. A provider in good standing is surfaced to more buyers, earns more, and builds a business that the cooperative can confidently recommend.",
            ],
        ],
        'materials' => [
            ['title' => 'Accreditation Evidence Checklist', 'type' => 'checklist', 'notes' => 'Registration, tax ID, contacts, product evidence, and file standards.'],
            ['title' => 'Coverage Update Form', 'type' => 'template', 'notes' => 'Request coverage changes with evidence and lead times.'],
            ['title' => 'Listing Standards Quick Card', 'type' => 'reference', 'notes' => 'Title, category, unit, price, and photo standards for every listing.'],
        ],
    ],

    20 => [
        'overview' => 'NATCODEV Academy Full Flow Test Course is the end-to-end QA fixture for the Academy. It validates public listing, learner registration, free enrollment, lesson completion, assessment submission, certificate eligibility, and catalog discovery. It is intentionally visible to every Academy stakeholder.',
        'duration' => 95,
        'lessons' => [
            [
                'title' => 'Catalog Discovery and Registration',
                'summary' => 'Confirm the course is listed and enroll as a learner.',
                'duration' => 30,
                'content' => "This first module validates that the course appears correctly in the public Academy catalog. Open the Academy catalog, search for the course title, and confirm the card shows the title, description, free price, and category. Pagination should be exercised: browse to the page where the course appears and open it from there.\n\nOn the course detail screen, confirm the overview, the lesson list, and the duration render correctly. Enroll using the register action; as a free course, enrollment should complete without a payment step. After enrollment, the course should appear in your learner dashboard under in-progress learning.\n\nIf the course does not appear in the catalog, or the detail screen shows missing fields, that is a listing defect: note the course ID and report it through the support desk with a screenshot. Catalog visibility is the entry point for every real learner, so it is tested first.\n\nFinally, confirm your registration status shows registered or in_progress in the dashboard. This completes the discovery and registration leg of the flow; the next modules cover lessons and assessment.",
            ],
            [
                'title' => 'Lesson Completion Flow',
                'summary' => 'Open lessons, mark them complete, and watch progress update.',
                'duration' => 35,
                'content' => "This module validates the lesson flow. From the dashboard, open the course's lesson screen. Each lesson should display its title, summary, duration, and full content body. The first lesson should be marked required, and the lesson list should show your progress as you move through it.\n\nOpen the first lesson, read the content, and mark it complete. Return to the lesson list and confirm the completion state and the progress percentage updated. Continue through every lesson in order, confirming that each completed lesson stays marked and that the overall progress rises toward one hundred percent.\n\nProgress tracking matters because certificates depend on it: a learner cannot request a certificate until the required lessons are complete. If progress does not update, or a lesson cannot be marked complete, that is a tracking defect; note the lesson ID and report it with the course ID.\n\nFinish all lessons and confirm the course shows as completed in your learning history. The completion state is what unlocks the assessment and, afterwards, the certificate request. This completes the lesson leg of the flow.",
            ],
            [
                'title' => 'Assessment and Certificate Flow',
                'summary' => 'Submit the assessment and request the certificate.',
                'duration' => 30,
                'content' => "This final module validates the assessment and certificate flow. Open the assessment from the course screen, confirm the instructions and pass score are shown, and answer every question. Submit the assessment; the result screen should show your score and whether you passed.\n\nIf the score is below the pass mark, review the lessons and retake the assessment; the platform allows multiple attempts up to the configured maximum. When you pass, the course becomes eligible for a certificate. Request the certificate from the certificates screen and confirm the request status shows pending or eligible.\n\nCertificate requests feed the certificate queue, where an approver reviews and issues the certificate. For this QA course, approval is automatic or handled by the test administrator; confirm the certificate reference is generated and the certificate appears in your certificates list.\n\nFinally, verify the transaction and support screens reflect the flow: a free enrollment should show a zero-amount transaction, and the support desk should be reachable from the course screen. This completes the full-flow test from catalog to certificate.",
            ],
        ],
        'materials' => [
            ['title' => 'Full Flow Test Script', 'type' => 'guide', 'notes' => 'Step-by-step QA script for catalog, enrollment, lessons, assessment, certificate.'],
            ['title' => 'Defect Report Template', 'type' => 'template', 'notes' => 'Course ID, screen, steps, expected vs actual, screenshot.'],
        ],
    ],
];
