/* =========================================================
   Attendance Management System — Client-side behaviour
   Plain JavaScript, no libraries.
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    /* ---- Confirm before delete ---- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    /* ---- Course -> Subject dependent dropdown (attendance marking page) ---- */
    var courseSelect = document.getElementById('course_id');
    var subjectSelect = document.getElementById('subject_id');
    if (courseSelect && subjectSelect) {
        courseSelect.addEventListener('change', function () {
            loadSubjectsForCourse(courseSelect.value, subjectSelect);
        });
    }

    /* ---- Mark all Present / Absent buttons ---- */
    var markAllPresent = document.getElementById('mark-all-present');
    var markAllAbsent = document.getElementById('mark-all-absent');

    if (markAllPresent) {
        markAllPresent.addEventListener('click', function () {
            setAllStatus('Present');
        });
    }
    if (markAllAbsent) {
        markAllAbsent.addEventListener('click', function () {
            setAllStatus('Absent');
        });
    }

    /* ---- Simple client-side table search ---- */
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        var targetId = input.getAttribute('data-table-search');
        var table = document.getElementById(targetId);
        if (!table) return;
        input.addEventListener('keyup', function () {
            var q = input.value.trim().toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    });
});

function setAllStatus(status) {
    var radios = document.querySelectorAll('input[type=radio][value="' + status + '"]');
    radios.forEach(function (r) { r.checked = true; });
}

function loadSubjectsForCourse(courseId, subjectSelect) {
    subjectSelect.innerHTML = '<option value="">Loading...</option>';

    if (!courseId) {
        subjectSelect.innerHTML = '<option value="">-- Select course first --</option>';
        return;
    }

    fetch('get_subjects.php?course_id=' + encodeURIComponent(courseId))
        .then(function (res) { return res.json(); })
        .then(function (data) {
            subjectSelect.innerHTML = '';
            if (data.length === 0) {
                subjectSelect.innerHTML = '<option value="">No subjects for this course</option>';
                return;
            }
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- Select subject --';
            subjectSelect.appendChild(placeholder);

            data.forEach(function (subj) {
                var opt = document.createElement('option');
                opt.value = subj.id;
                opt.textContent = subj.subject_name;
                subjectSelect.appendChild(opt);
            });

            // Reload the student list once a subject is chosen
            var studentListArea = document.getElementById('student-list-area');
            if (studentListArea) {
                subjectSelect.addEventListener('change', triggerAttendanceFormSubmitIfReady);
            }
        })
        .catch(function () {
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        });
}

function triggerAttendanceFormSubmitIfReady() {
    var courseId = document.getElementById('course_id').value;
    var subjectId = document.getElementById('subject_id').value;
    var dateVal = document.getElementById('attendance_date').value;
    if (courseId && subjectId && dateVal) {
        document.getElementById('attendance-filter-form').submit();
    }
}
