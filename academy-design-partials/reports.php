    <div class="page" id="page-reports">
      <div class="page-header">
        <div><div class="page-title">Reports</div><div class="page-subtitle">Analytics and insights across the academy</div></div>
        <button class="btn btn-primary" onclick="showToast('Generating report...')">📊 Generate Report</button>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Enrollment Trends</div></div>
          <div class="card-body">
            <div style="display:flex;align-items:end;gap:8px;height:180px;padding:20px 0">
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:40%;position:relative"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jan</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:55%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Feb</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:48%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Mar</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:72%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Apr</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:65%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">May</div></div>
              <div style="flex:1;background:var(--green-500);border-radius:6px 6px 0 0;height:85%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jun</div></div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Completion by Program</div></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px">
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Career Onboarding</span><span style="font-weight:600">85%</span></div><div class="progress-bar"><div class="progress-fill" style="width:85%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Pre-Service Accreditation</span><span style="font-weight:600">62%</span></div><div class="progress-bar"><div class="progress-fill" style="width:62%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Field Staff Certification</span><span style="font-weight:600">74%</span></div><div class="progress-bar"><div class="progress-fill" style="width:74%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>HQ Skills Certification</span><span style="font-weight:600">91%</span></div><div class="progress-bar"><div class="progress-fill" style="width:91%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>State Coordinator Ops</span><span style="font-weight:600">45%</span></div><div class="progress-bar"><div class="progress-fill" style="width:45%"></div></div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Available Reports</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Report</th><th>Description</th><th>Last Generated</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Learner Progress Report</strong></td><td>Individual and cohort progress metrics</td><td>Jun 4, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Financial Summary</strong></td><td>Revenue, refunds, and outstanding payments</td><td>Jun 1, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Instructor Performance</strong></td><td>Ratings, session counts, and learner feedback</td><td>May 28, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Certificate Audit</strong></td><td>All issued certificates with verification status</td><td>May 25, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Attendance Analytics</strong></td><td>Session attendance patterns and trends</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
