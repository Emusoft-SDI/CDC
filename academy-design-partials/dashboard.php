    <div class="page active" id="page-dashboard">
      <div class="page-header">
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-subtitle">Overview of your academy performance</div>
        </div>
        <button class="btn btn-primary" onclick="showToast('Report exported')"> Export Report</button>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Total Learners</div>
            <div class="stat-card-icon" style="background:#dbeafe;color:#1e40af"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          </div>
          <div class="stat-card-value">3,624</div>
          <div class="stat-card-change up">↑ 12.5% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Active Enrollments</div>
            <div class="stat-card-icon" style="background:#dcfce7;color:#166534"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          </div>
          <div class="stat-card-value">2,479</div>
          <div class="stat-card-change up">↑ 8.2% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Certificates Issued</div>
            <div class="stat-card-icon" style="background:#f3e8ff;color:#6b21a8"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div>
          </div>
          <div class="stat-card-value">1,532</div>
          <div class="stat-card-change up">↑ 15.3% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Completion Rate</div>
            <div class="stat-card-icon" style="background:#fef3c7;color:#92400e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
          </div>
          <div class="stat-card-value">68.4%</div>
          <div class="stat-card-change down">↓ 2.1% from last month</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Enrollments</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('learners')">View All</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Learner</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div>Aisha Koroma</div></td><td>Power BI Essentials</td><td>Jun 4, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div>Tunde Salami</div></td><td>Python for Data Science</td><td>Jun 3, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div>Miriam Osei</div></td><td>Agile Project Management</td><td>Jun 2, 2026</td><td><span class="status-badge status-pending">Pending</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div>Fatima Ndiaye</div></td><td>UX/UI Design Fundamentals</td><td>Jun 1, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Upcoming Live Sessions</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('cohorts')">View Calendar</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Session</th><th>Instructor</th><th>Date</th><th>Seats</th></tr></thead>
              <tbody>
                <tr><td>Python Capstone Review</td><td>Dr. Adebayo</td><td>Jun 8, 10:00</td><td>12/30</td></tr>
                <tr><td>Power BI Dashboard Lab</td><td>Prof. Mensah</td><td>Jun 9, 14:00</td><td>8/25</td></tr>
                <tr><td>Agile Sprint Planning</td><td>Ms. Okonkwo</td><td>Jun 10, 09:00</td><td>20/25</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
