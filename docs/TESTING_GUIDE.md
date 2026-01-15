# ✅ System Testing Guide

**Version:** 2.0  
**Last Updated:** 2026-01-13  
**Purpose:** Comprehensive testing checklist for production readiness

---

## 🎯 Testing Objectives

1. Verify all functionality works correctly
2. Ensure security features are active
3. Validate performance improvements
4. Confirm browser compatibility
5. Test error handling and edge cases

---

## 📋 Test Execution Checklist

### ✅ Authentication & Security Tests

#### Login Flow
- [ ] **T1.1:** Login with valid credentials succeeds
- [ ] **T1.2:** Login with invalid credentials fails with appropriate message
- [ ] **T1.3:** CSRF token is present in login form
- [ ] **T1.4:** CSRF token validation works (try POST without token → should fail)
- [ ] **T1.5:** Session is created on successful login
- [ ] **T1.6:** Session fingerprint is initialized
- [ ] **T1.7:** Logout destroys session completely

#### Rate Limiting
- [ ] **T2.1:** First failed login attempt is recorded
- [ ] **T2.2:** After 5 failed attempts, user is blocked for 5 minutes
- [ ] **T2.3:** After 6-10 failures, block time increases to 15 minutes
- [ ] **T2.4:** Successful login resets the counter
- [ ] **T2.5:** Block message shows time remaining
- [ ] **T2.6:** Different usernames are tracked separately

#### Session Security
- [ ] **T3.1:** Session timeout after 30 minutes of inactivity
- [ ] **T3.2:** Session regenerates on login
- [ ] **T3.3:** Changed User-Agent breaks session (fingerprint mismatch)
- [ ] **T3.4:** Concurrent sessions from same user work correctly
- [ ] **T3.5:** Session cookies have httponly, secure, samesite flags

---

### ✅ API Layer Tests

#### Projects API
- [ ] **T4.1:** `API.projects.getAll()` returns all projects
- [ ] **T4.2:** `API.projects.get(id)` returns single project
- [ ] **T4.3:** `API.projects.create(data)` creates new project
- [ ] **T4.4:** `API.projects.update(id, data)` updates project
- [ ] **T4.5:** `API.projects.delete(id)` deletes project
- [ ] **T4.6:** `API.projects.createSnapshot()` creates version
- [ ] **T4.7:** `API.projects.restoreSnapshot()` restores version
- [ ] **T4.8:** `API.projects.saveCoverImage()` uploads image

#### Customers API
- [ ] **T5.1:** `API.customers.getAll()` returns all customers
- [ ] **T5.2:** `API.customers.create(data)` creates customer
- [ ] **T5.3:** `API.customers.update(id, data)` updates customer
- [ ] **T5.4:** `API.customers.delete(id)` deletes customer
- [ ] **T5.5:** `API.customers.search(query)` finds customers

#### Error Handling
- [ ] **T6.1:** Network timeout shows appropriate error message
- [ ] **T6.2:** Server error (500) shows user-friendly message
- [ ] **T6.3:** Validation errors show field-specific messages
- [ ] **T6.4:** Retry logic works on transient failures
- [ ] **T6.5:** CSRF failure shows security warning

---

### ✅ Database Optimization Tests

#### Views
- [ ] **T7.1:** `v_project_overview` returns correct aggregated data
- [ ] **T7.2:** `v_building_elements_hierarchy` shows correct tree structure
- [ ] **T7.3:** `v_budget_summary` calculates totals correctly
- [ ] **T7.4:** `v_media_overview` includes all media files
- [ ] **T7.5:** `v_active_locks` shows only active locks

#### Stored Procedures
- [ ] **T8.1:** `sp_clone_project()` creates exact copy with all elements
- [ ] **T8.2:** `sp_calculate_project_totals()` returns accurate sums
- [ ] **T8.3:** `sp_bulk_update_elements()` updates multiple records
- [ ] **T8.4:** `sp_clean_expired_locks()` removes old locks
- [ ] **T8.5:** `sp_get_element_path()` returns full hierarchy path
- [ ] **T8.6:** `sp_recalculate_sort_order()` fixes gaps in sequences

#### Performance
- [ ] **T9.1:** Project overview query < 50ms (was ~180ms)
- [ ] **T9.2:** Element hierarchy query < 50ms (was ~200ms)
- [ ] **T9.3:** Budget summary query < 50ms (was ~180ms)
- [ ] **T9.4:** Dashboard loads < 1 second
- [ ] **T9.5:** No N+1 query problems in lists

---

### ✅ UI/UX Tests

#### Window Manager
- [ ] **T10.1:** Windows can be dragged by header
- [ ] **T10.2:** Windows can be resized from bottom-right corner
- [ ] **T10.3:** Double-clicking header maximizes window
- [ ] **T10.4:** Double-clicking again restores original size
- [ ] **T10.5:** Minimize button sends window to dock
- [ ] **T10.6:** Clicking dock icon restores window
- [ ] **T10.7:** Multiple windows can be open simultaneously
- [ ] **T10.8:** Windows maintain z-index (focus) correctly
- [ ] **T10.9:** Window min size enforced (300x200px)
- [ ] **T10.10:** Close button removes window completely

#### Forms & Validation
- [ ] **T11.1:** Required fields show validation messages
- [ ] **T11.2:** Date pickers work correctly
- [ ] **T11.3:** File uploads show progress
- [ ] **T11.4:** Auto-save works for building elements
- [ ] **T11.5:** WYSIWYG editors load and save correctly

#### Responsive Design
- [ ] **T12.1:** Layout works on desktop (1920x1080)
- [ ] **T12.2:** Layout works on laptop (1366x768)
- [ ] **T12.3:** Layout works on tablet (768x1024)
- [ ] **T12.4:** Layout works on mobile (375x667)
- [ ] **T12.5:** Touch interactions work on mobile

---

### ✅ Feature-Specific Tests

#### Projects Module
- [ ] **T13.1:** Create new project with all fields
- [ ] **T13.2:** Edit project updates database
- [ ] **T13.3:** Delete project removes all related data (CASCADE)
- [ ] **T13.4:** Cover image upload and annotation works
- [ ] **T13.5:** Project snapshot creation works
- [ ] **T13.6:** Project snapshot restoration works
- [ ] **T13.7:** Client search finds correct results
- [ ] **T13.8:** Team member management works

#### Building Elements Module
- [ ] **T14.1:** Create element at root level
- [ ] **T14.2:** Create child element (hierarchy)
- [ ] **T14.3:** Drag-drop reordering works
- [ ] **T14.4:** Field updates save correctly
- [ ] **T14.5:** Real-time collaborative editing (locking) works
- [ ] **T14.6:** Media upload and sorting works
- [ ] **T14.7:** Budget item creation works
- [ ] **T14.8:** Element deletion cascades to children
- [ ] **T14.9:** Parent-child relationships maintained

#### Customers Module
- [ ] **T15.1:** Create customer
- [ ] **T15.2:** Edit customer
- [ ] **T15.3:** Delete customer (with confirmation)
- [ ] **T15.4:** Search customers
- [ ] **T15.5:** View customer projects
- [ ] **T15.6:** Customer deletion blocks if has projects

#### Reports Module
- [ ] **T16.1:** Generate standard report
- [ ] **T16.2:** Generate from template
- [ ] **T16.3:** Export to Excel
- [ ] **T16.4:** Print preview works
- [ ] **T16.5:** Report includes all sections
- [ ] **T16.6:** Budget calculations correct

---

### ✅ Security Penetration Tests

#### XSS Tests
- [ ] **T17.1:** `<script>alert('XSS')</script>` in text fields is escaped
- [ ] **T17.2:** HTML tags in descriptions are sanitized
- [ ] **T17.3:** JavaScript in uploaded filenames is blocked
- [ ] **T17.4:** Output escaping works in templates

#### CSRF Tests
- [ ] **T18.1:** POST without token fails
- [ ] **T18.2:** POST with wrong token fails
- [ ] **T18.3:** POST with expired token fails
- [ ] **T18.4:** Token regenerates on form reload

#### SQL Injection Tests
- [ ] **T19.1:** `'; DROP TABLE users--` in inputs doesn't break
- [ ] **T19.2:** `1 OR 1=1` in ID parameters doesn't leak data
- [ ] **T19.3:** All queries use prepared statements

#### File Upload Tests
- [ ] **T20.1:** PHP file upload is blocked
- [ ] **T20.2:** File size limit enforced
- [ ] **T20.3:** MIME type validation works
- [ ] **T20.4:** Double extension (file.php.jpg) blocked
- [ ] **T20.5:** Only allowed file types accepted

#### Session Tests
- [ ] **T21.1:** Session hijack attempt detected
- [ ] **T21.2:** Session fixation prevented
- [ ] **T21.3:** Concurrent logins handled correctly

---

### ✅ Error Handling Tests

#### Application Errors
- [ ] **T22.1:** 404 page shows for missing pages
- [ ] **T22.2:** 500 error shows user-friendly message
- [ ] **T22.3:** Database connection error handled gracefully
- [ ] **T22.4:** Missing file errors logged

#### User Errors
- [ ] **T23.1:** Invalid form data shows validation messages
- [ ] **T23.2:** Duplicate entries prevented
- [ ] **T23.3:** Required fields enforced
- [ ] **T23.4:** Foreign key violations handled

---

### ✅ Browser Compatibility Tests

#### Desktop Browsers
- [ ] **T24.1:** Chrome 120+ (Windows/Mac/Linux)
- [ ] **T24.2:** Firefox 121+ (Windows/Mac/Linux)
- [ ] **T24.3:** Edge 120+ (Windows)
- [ ] **T24.4:** Safari 17+ (Mac)

#### Mobile Browsers
- [ ] **T25.1:** Safari iOS 17+
- [ ] **T25.2:** Chrome Android 120+
- [ ] **T25.3:** Samsung Internet 23+

#### Features to Test
- [ ] JavaScript modules load
- [ ] API calls work
- [ ] CSS renders correctly
- [ ] Touch/click events work
- [ ] File uploads work
- [ ] Modals/windows work

---

## 📊 Test Results Template

### Test Session Info
- **Date:** _____________
- **Tester:** _____________
- **Environment:** Production / Staging / Local
- **Browser:** _____________
- **OS:** _____________

### Results Summary
| Category | Total Tests | Passed | Failed | Skipped |
|----------|-------------|--------|--------|---------|
| Authentication | 15 | | | |
| API Layer | 15 | | | |
| Database | 15 | | | |
| UI/UX | 20 | | | |
| Features | 30 | | | |
| Security | 20 | | | |
| Errors | 10 | | | |
| Browsers | 7 | | | |
| **TOTAL** | **132** | | | |

### Issues Found
| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| 001 | High | | Open / Fixed |
| 002 | Medium | | |
| 003 | Low | | |

### Sign-Off
- [ ] All critical tests passed
- [ ] Known issues documented
- [ ] Fixes deployed and re-tested
- [ ] System ready for production

**Approved By:** _____________  
**Date:** _____________

---

## 🎯 Acceptance Criteria

### Must Pass (Critical)
- All authentication tests
- All security tests  
- All API layer tests
- All CASCADE delete tests
- Browser compatibility (Chrome, Firefox, Edge)

### Should Pass (Important)
- All performance tests
- All UI/UX tests
- All feature tests
- Mobile compatibility

### Nice to Have (Optional)
- Advanced browser tests
- Stress testing
- Load testing

---

**Test Coverage:** 132 test cases  
**Automation:** Manual (future: consider Selenium/Cypress)  
**Frequency:** Before each deployment
