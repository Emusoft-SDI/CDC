<div class="modal-overlay" id="programModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Program</div><button class="btn-icon" onclick="closeModal('programModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Program Name</label><input class="form-input" placeholder="e.g. Career Onboarding Program"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration</label><input class="form-input" placeholder="e.g. 12 weeks"></div>
        <div class="form-group"><label class="form-label">Max Learners</label><input class="form-input" type="number" placeholder="500"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Program description..."></textarea></div>
      <div class="form-group"><label class="form-label">Certificate Pathway</label><select class="form-select"><option>Select pathway...</option><option>Data Analyst</option><option>Project Manager</option><option>UX Designer</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('programModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('programModal');showToast('Program created successfully')">Create Program</button></div>
  </div>
</div>

<div class="modal-overlay" id="courseModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Course</div><button class="btn-icon" onclick="closeModal('courseModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Course Title</label><input class="form-input" placeholder="e.g. Power BI Essentials"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
        <div class="form-group"><label class="form-label">Instructor</label><select class="form-select"><option>Dr. Adebayo</option><option>Prof. Mensah</option><option>Ms. Okonkwo</option></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration (hours)</label><input class="form-input" type="number" placeholder="40"></div>
        <div class="form-group"><label class="form-label">Price ($)</label><input class="form-input" type="number" placeholder="299"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Course description..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('courseModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('courseModal');showToast('Course created')">Create Course</button></div>
  </div>
</div>

<div class="modal-overlay" id="lessonModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add New Lesson</div><button class="btn-icon" onclick="closeModal('lessonModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Lesson Title</label><input class="form-input" placeholder="e.g. Introduction to Power BI"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>Power BI Essentials</option><option>Python for Data Science</option></select></div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Video</option><option>Reading</option><option>Quiz</option><option>Assignment</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" type="number" placeholder="30"></div>
      <div class="form-group"><label class="form-label">Content / Notes</label><textarea class="form-textarea" placeholder="Lesson content..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('lessonModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('lessonModal');showToast('Lesson added')">Add Lesson</button></div>
  </div>
</div>

<div class="modal-overlay" id="assessmentModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Assessment</div><button class="btn-icon" onclick="closeModal('assessmentModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Assessment Title</label><input class="form-input" placeholder="e.g. Mid-Term Exam"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>Power BI Essentials</option><option>Python for Data Science</option></select></div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Quiz</option><option>Exam</option><option>Assignment</option><option>Project</option></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Number of Questions</label><input class="form-input" type="number" placeholder="25"></div>
        <div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" type="number" placeholder="60"></div>
      </div>
      <div class="form-group"><label class="form-label">Passing Score (%)</label><input class="form-input" type="number" placeholder="70"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('assessmentModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('assessmentModal');showToast('Assessment created')">Create</button></div>
  </div>
</div>

<div class="modal-overlay" id="cohortModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Cohort</div><button class="btn-icon" onclick="closeModal('cohortModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Cohort Name</label><input class="form-input" placeholder="e.g. Cohort 2026-F"></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date</label><input class="form-input" type="date"></div>
        <div class="form-group"><label class="form-label">End Date</label><input class="form-input" type="date"></div>
      </div>
      <div class="form-group"><label class="form-label">Max Learners</label><input class="form-input" type="number" placeholder="300"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('cohortModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('cohortModal');showToast('Cohort created')">Create Cohort</button></div>
  </div>
</div>

<div class="modal-overlay" id="instructorModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Instructor</div><button class="btn-icon" onclick="closeModal('instructorModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input"></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div>
      <div class="form-group"><label class="form-label">Specialization</label><input class="form-input" placeholder="e.g. Data Science"></div>
      <div class="form-group"><label class="form-label">Bio</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('instructorModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('instructorModal');showToast('Instructor added')">Add Instructor</button></div>
  </div>
</div>

<div class="modal-overlay" id="reminderModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Reminder</div><button class="btn-icon" onclick="closeModal('reminderModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Reminder Title</label><input class="form-input" placeholder="e.g. Assignment Due Tomorrow"></div>
      <div class="form-group"><label class="form-label">Target Audience</label><select class="form-select"><option>All Learners</option><option>Specific Cohort</option><option>Specific Course</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Schedule Date</label><input class="form-input" type="date"></div>
        <div class="form-group"><label class="form-label">Time</label><input class="form-input" type="time"></div>
      </div>
      <div class="form-group"><label class="form-label">Channel</label><select class="form-select"><option>Email</option><option>SMS</option><option>Push Notification</option><option>Email + SMS</option></select></div>
      <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" placeholder="Reminder message..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('reminderModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('reminderModal');showToast('Reminder scheduled')">Schedule</button></div>
  </div>
</div>

<div class="modal-overlay" id="learnerModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Learner</div><button class="btn-icon" onclick="closeModal('learnerModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input"></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
      <div class="form-group"><label class="form-label">Cohort</label><select class="form-select"><option>Cohort 2026-B</option><option>Cohort 2026-C</option><option>Cohort 2026-D</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('learnerModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('learnerModal');showToast('Learner enrolled')">Enroll Learner</button></div>
  </div>
</div>

<div class="modal-overlay" id="certificateModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Issue Certificate</div><button class="btn-icon" onclick="closeModal('certificateModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Learner</label><select class="form-select"><option>Fatima Ndiaye</option><option>Tunde Salami</option><option>Aisha Koroma</option></select></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Pre-Service Accreditation</option><option>Career Onboarding</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Grade</label><input class="form-input" placeholder="e.g. A (95%)"></div>
        <div class="form-group"><label class="form-label">Issue Date</label><input class="form-input" type="date"></div>
      </div>
      <div class="form-group"><label class="form-label">Certificate ID</label><input class="form-input" value="CERT-2026-0848" readonly style="background:var(--bg)"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('certificateModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('certificateModal');showToast('Certificate issued')">Issue Certificate</button></div>
  </div>
</div>

<div class="modal-overlay" id="pathwayModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Certificate Pathway</div><button class="btn-icon" onclick="closeModal('pathwayModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Pathway Name</label><input class="form-input" placeholder="e.g. Data Analyst Pathway"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration</label><input class="form-input" placeholder="6 months"></div>
        <div class="form-group"><label class="form-label">Certificate Name</label><input class="form-input" placeholder="Certified Data Analyst"></div>
      </div>
      <div class="form-group"><label class="form-label">Required Courses</label><select class="form-select" multiple style="min-height:100px"><option>Power BI Essentials</option><option>Python for Data Science</option><option>SQL Fundamentals</option><option>Statistics 101</option></select></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('pathwayModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('pathwayModal');showToast('Pathway created')">Create Pathway</button></div>
  </div>
</div>

<div class="modal-overlay" id="refundModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">New Refund Request</div><button class="btn-icon" onclick="closeModal('refundModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Learner</label><select class="form-select"><option>Sarah Koffi</option><option>David Mensah</option><option>Amina Yusuf</option></select></div>
      <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>HQ Skills Certification</option><option>Python for Data Science</option><option>Agile Project Management</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Amount ($)</label><input class="form-input" type="number" placeholder="450"></div>
        <div class="form-group"><label class="form-label">Reason</label><select class="form-select"><option>Course mismatch</option><option>Technical issues</option><option>Personal reasons</option><option>Duplicate enrollment</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('refundModal');showToast('Refund request submitted')">Submit Request</button></div>
  </div>
</div>
