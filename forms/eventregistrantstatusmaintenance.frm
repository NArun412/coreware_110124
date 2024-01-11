<div id="_maintenance_form">

%field:event_id_display,description,finalize,start_date%
%basic_form_line%
%end repeat%

<p>Check this box to not set any attendance statuses yet, if, for instance, the page is being used to check students in.</p>
<p><input type="checkbox" id='no_status' value="1"></input> <label for="no_status">No Attendance Status Yet</label></p>
<h2>Class Attendees</h2>

%field:event_registrants%
%basic_form_line%

</div> <!-- maintenance_form -->
