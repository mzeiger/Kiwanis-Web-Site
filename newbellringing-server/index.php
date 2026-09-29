<?php require_once __DIR__ . '/inc/pusher_notify.php'; ?>
<!-- index.php for BellRinging project -->

<!DOCTYPE html>

<html>


<style type="text/css">

  :root {
    --brand-teal: #339999;
    --brand-teal-dark: #2a7a7a;
    --brand-red: #c41e3a;
    --surface: #ffffff;
    --page-bg: #f4f7f6;
    --text-muted: #5c6569;
    --border: #d8dee0;
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.1);
    --radius: 10px;
  }

  body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.55;
    color: #2c3335;
    background: var(--page-bg);
    padding-top: 52px;
  }

  .page-main {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.25rem 2.5rem;
  }

  .page-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 1.75rem 0 1rem;
    flex-wrap: wrap;
  }

  .page-hero img {
    width: 210px;
    height: auto;
    flex-shrink: 0;
  }

  .page-hero h1 {
    margin: 0;
    text-align: center;
    color: var(--brand-red);
    font-size: clamp(1.35rem, 3.5vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.25;
  }

  .welcome-card {
    max-width: 680px;
    margin: 0 auto 2rem;
    padding: 1.5rem 1.75rem;
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--brand-teal);
  }

  .welcome-card h3 {
    margin: 0 0 0.85rem;
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--brand-teal-dark);
  }

  .welcome-card ul {
    margin: 0 0 1.25rem;
    padding-left: 1.35rem;
    color: var(--text-muted);
  }

  .welcome-card li {
    margin-bottom: 0.45rem;
  }

  .welcome-card li:last-child {
    margin-bottom: 0;
  }

  .welcome-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem 1rem;
    margin: 0;
    padding-top: 0.25rem;
  }

  .welcome-action-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }

  .welcome-btn {
    display: inline-block;
    padding: 0.55rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: background 0.15s, transform 0.1s;
  }

  .welcome-btn-primary {
    background: var(--brand-teal);
    color: #fff;
  }

  .welcome-btn-primary:hover {
    background: var(--brand-teal-dark);
    color: #fff;
  }

  .welcome-card a.welcome-btn-primary,
  .welcome-card a.welcome-btn-primary:hover {
    color: #fff;
  }

  .welcome-btn-secondary {
    background: #fff;
    color: var(--brand-teal-dark);
    border: 2px solid var(--brand-teal);
  }

  .welcome-btn-secondary:hover {
    background: #eef6f6;
    color: var(--brand-teal-dark);
  }

  .error    /* This is the jQuery Validate() class for error messages  */
  {
    color: #CC0000;
    font-size: small;
    padding-left: 3px;
    text-align: left;
  }

  input.rounded2
  {
      border: 1px solid var(--border);
      border-radius: 8px;
      box-shadow: none;
      font-size: 16px;
      padding: 0.5rem 0.75rem;
      outline: 0;
      -webkit-appearance: none;
      display: inline-block;
      width: 250px;
      transition: border-color 0.15s, box-shadow 0.15s;
  }

  input.rounded2:focus {
      border-color: var(--brand-teal);
      box-shadow: 0 0 0 3px rgba(51, 153, 153, 0.2);
  }

  #enrollDialog label,
  #signInDialog label,
  #forgotPasswordDialog label {
      text-align: right;
      padding-right: 12px;
      display: inline-block;
      min-width: 180px;
      font-weight: 500;
      color: #3d4548;
      vertical-align: middle;
  }

  label {
      text-align:right;
      padding-right:20px;
      display:inline-block;
      min-width:200px;
  }

  #enrollDialog form,
  #signInDialog form,
  #forgotPasswordDialog form {
      padding: 0.25rem 0.5rem;
  }

  #enrollDialog form {
      padding-left: 1.25rem;
  }

  #enrollDialog input[type="submit"],
  #signInDialog input[type="submit"],
  #forgotPasswordDialog input[type="submit"] {
      background: var(--brand-teal);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0.55rem 1.4rem;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
  }

  #enrollDialog input[type="submit"]:hover,
  #signInDialog input[type="submit"]:hover,
  #forgotPasswordDialog input[type="submit"]:hover {
      background: var(--brand-teal-dark);
  }

  #signInDialog #forgotPasswordTrigger {
      color: var(--brand-teal-dark);
      font-weight: 500;
      text-decoration: none;
      padding: 0.35rem 0;
  }

  #signInDialog #forgotPasswordTrigger:hover {
      text-decoration: underline;
  }

  #forgotPasswordDialog #forgotPasswordEmailInput {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.5rem 0.75rem;
      font-size: 16px;
      width: 250px;
  }

  #forgotPasswordDialog #forgotPasswordEmailInput:focus {
      border-color: var(--brand-teal);
      outline: none;
      box-shadow: 0 0 0 3px rgba(51, 153, 153, 0.2);
  }

  .ui-dialog {
      border-radius: var(--radius) !important;
      box-shadow: var(--shadow-md) !important;
      border: none !important;
      padding: 0;
      overflow: hidden;
  }

  .ui-dialog .ui-dialog-titlebar {
      background: var(--brand-teal) !important;
      color: #fff !important;
      border: none !important;
      border-radius: 0 !important;
      font-weight: 600;
      padding: 0.65rem 1rem !important;
  }

  .ui-dialog .ui-dialog-titlebar-close {
      color: #fff !important;
  }

  .ui-dialog .ui-dialog-content {
      padding: 1.25rem 1.5rem !important;
  }

  .calendarTableLbl
  {
    border-style: solid;
    border-color: #B4886B;
    font-weight: bold;
    display: block;
    width: 132px;
    height: 25px;
    text-align: center;
    color: green;
  }

.calendarTableLblRed
  {
    border-style: solid;
    border-color: #B4886B;
    font-weight: bold;
    display: block;
    width: 132px;
    height: 25px;
    text-align: center;
    color: red;
  }

.calendarClass td:first-child {
    white-space: nowrap;
    min-width: 155px;
    padding-left: 10px;
}

.calendarClass td:nth-child(2) {
    white-space: nowrap;
    min-width: 120px;
}

.calendarClass tbody tr:hover
{
    background-color: lightgrey;
}

#header{
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    padding: 0.45rem 1rem;
    min-height: 44px;
    width: 100%;
    box-sizing: border-box;
    background-color: var(--brand-teal);
    color: white;
    z-index: 999;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
}

#header a{
  color: white;
  text-decoration: none;
}

#header span {
  color: white;
}

#header .header-spacer {
  flex: 1;
  min-width: 0.5rem;
}

#helpButton,
#showScheduleButton {
  margin: 0;
  padding: 0.4rem 0.85rem;
  border: none;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.92);
  color: var(--brand-teal-dark);
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.15s;
}

#helpButton:hover,
#showScheduleButton:hover {
  background: #fff;
}

#signInInfo {
  color: #fff;
  font-weight: 500;
  font-size: 0.9rem;
  padding: 0.35rem 0;
  white-space: nowrap;
  flex-shrink: 0;
}

body.logged-out #signInInfo {
  display: none !important;
}

body.logged-in #signInInfo {
  display: inline-block !important;
}

@media (max-width: 900px) {
  #signInInfo {
    flex: 1 1 100%;
    text-align: left;
    white-space: normal;
    word-break: break-word;
  }
}

.welcome-card a {
  color: var(--brand-teal-dark);
}

.welcome-card a:hover {
  color: var(--brand-teal);
}

#allDaysCheckBox {
  margin-left: 0.25rem;
  vertical-align: middle;
}

.infoCursor {
    cursor: help;
}

.pwd-input-wrap {
    position: relative;
    display: inline-block;
    width: 250px;
}

.enroll-input-wrap {
    display: inline-block;
    width: 266px;
    margin-bottom: 6px;
}

.enroll-input-wrap input.rounded2 {
    width: 100%;
    box-sizing: border-box;
}

.enroll-pwd-wrap {
    width: 266px;
    margin-bottom: 6px;
}

.pwd-input-wrap input.rounded2 {
    width: 100%;
    box-sizing: border-box;
    padding-right: 2.5rem;
}

.pwd-toggle-inside {
    position: absolute;
    right: 5px;
    top: 0;
    width: 2.5rem;
    height: 100%;
    border: none;
    background: transparent;
    color: #6c757d;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.25rem;
    z-index: 5;
    padding: 0;
}

.pwd-toggle-inside:hover,
.pwd-toggle-inside:focus-visible {
    color: #212529;
    background: rgba(0, 0, 0, 0.06);
    outline: none;
}

.pwd-toggle-inside i {
    font-size: 1.1rem;
    pointer-events: none;
}

#schedule-toast-container {
    position: fixed;
    bottom: 1.25rem;
    right: 1.25rem;
    z-index: 10000;
    pointer-events: none;
}

.schedule-toast {
    background: var(--brand-teal);
    color: #fff;
    padding: 0.65rem 1rem;
    border-radius: 8px;
    box-shadow: var(--shadow-md);
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.35;
    opacity: 0;
    transform: translateY(0.5rem);
    transition: opacity 0.25s ease, transform 0.25s ease;
    max-width: 320px;
}

.schedule-toast.show {
    opacity: 1;
    transform: translateY(0);
}


</style>

<head>

    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/css/tooltipster.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/css/themes/tooltipster-shadow.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/css/themes/tooltipster-punk.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/css/themes/tooltipster-noir.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/css/themes/tooltipster-light.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" type="text/css" href="css/jquery.datepick.css" />

    <title>Monument Hill Kiwanis Bell Ringing</title>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>
    <script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.min.js"></script>
    <script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.9/additional-methods.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/tooltipster/3.0.5/js/jquery.tooltipster.min.js"></script>
    <script type="text/javascript" src="https://cloud.github.com/downloads/digitalBush/jquery.maskedinput/jquery.maskedinput-1.3.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>

    <script type="text/javascript" src="js/jquery.plugin.js"></script>
    <script type="text/javascript" src="js/jquery.datepick.min.js"></script>
    <script type="text/javascript" src="js/jquery.mask.min.js"></script>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

      <script>
      // var confirm_ret;  // used to get result of confirm jQuery dialog

      var schedulePusher = null;
      var schedulePusherChannel = null;
      var PUSHER_KEY = <?php echo json_encode(defined('PUSHER_KEY') ? PUSHER_KEY : ''); ?>;
      var PUSHER_CLUSTER = <?php echo json_encode(defined('PUSHER_CLUSTER') ? PUSHER_CLUSTER : 'us2'); ?>;
      var PUSHER_SCHEDULE_CHANNEL = <?php echo json_encode(defined('PUSHER_SCHEDULE_CHANNEL') ? PUSHER_SCHEDULE_CHANNEL : 'bellringing-schedule'); ?>;
      var PUSHER_SCHEDULE_EVENT = <?php echo json_encode(defined('PUSHER_SCHEDULE_EVENT') ? PUSHER_SCHEDULE_EVENT : 'schedule-changed'); ?>;
      var PUSHER_ENABLED = <?php echo pusherConfigIsValid() ? 'true' : 'false'; ?>;

      $(initialize);

      function initialize()
      {
          $("#bellringingYear").text(new Date().getFullYear());

          var _resetTokParams = new URLSearchParams(window.location.search);
          var _resetTok = _resetTokParams.get("token");
          if (_resetTok && /^[a-f0-9]{64}$/i.test(_resetTok)) {
              window.location.replace("reset_password.php?token=" + encodeURIComponent(_resetTok));
              return;
          }

          initializeToolTips();
          setAccordion(); // Sets up the basic page as a jQuery Accordion
          setRegisterValidate(); // Sets up validation for the "registration" form
          setSignInValidate(); // Sets up validation for the "Sign In" form
          setForgotPasswordValidate(); // Sets up validation for " Forgot Password form"
          setDialogs(); // Sets up the Sign In and Registration forms as dialogs
          setShowCalendarTrigger(); // Sets the "click" event for the "Show Calendar" anchor  to the showCalander() function
          $("#calendarDiv").on("click", ".calendarTableLbl", processCalendarClick);
          showResetSuccessMessage();
          wirePasswordToggles();
          $("#phone").mask("000-000-0000")

          $("#helpButton").click(function()
              {
                  window.open('help/help.html', 'Help', 'height=900,width=1300, location=0, scrollbars=1');
              }
          );
          $("#showScheduleButton").click(function()
            {
                showFullSchedule();
            }
          );
          $("#bellringingCalendar").hide(1); // Don't want the "Show Calendar" link to show until after successful sign in
          $("#allDaysSpan").hide(1);
          $("#showSchedule").hide(1);
          $("body").addClass("logged-out");
          $("#welcomeEnroll").click(function(e) {
              e.preventDefault();
              $("#enrollTrigger").click();
          });
          $("#welcomeSignIn").click(function(e) {
              e.preventDefault();
              $("#signInTrigger").click();
          });
          theCookie = $.cookie("MHKBR");
          if (typeof(theCookie) != "undefined")
          {
              obj = JSON.parse(theCookie);
              data = "emailSignIn=" + obj.username + "&pwdSignIn=" + obj.password + "&signIn=Sign+In&do_not_delete_cookie=true"
              $.post("signin.php", data, signInResult); //calls the signInResult() function after success
          }

      }         // end "initialize()"



      function showFullSchedule()
      {
        formData = $("#hiddenForm").serialize();
        window.open('showFullSchedule.php?' + formData, 'Full Schedule', 'height=900,width=1300, location=0, scrollbars=1, menubar=1, titlebar=1, toolbar=1');
      }

      function setDialogs() // Sets a number of dialogs and triggers (click events)
      {
          $("#enrollDialog").dialog(
              {
              modal: true, autoOpen: false, minWidth: 800, title: "Enroll",
              close: function()
                  {
                      $("#registerResult").html("");
                      $("#fname").val("");
                      $("#lname").val("");
                      $("#email").val("");
                      $("#email2").val("");
                      $("#phone").val("");
                      $("#pwd").val("");
                      $("#pwd2").val("");
                  }
                  ,
              open: function()
                  {
                      $("#signInDialog").dialog("close"); // just in case it's open
                  }
              }
          );

          $("#enrollTrigger").click(function()
              {
                  $("#enrollDialog").dialog("open");
              }
          );

          $("#signOutTrigger").click(signOut);

          $("#signInDialog").dialog(
              {
              modal: true, autoOpen: false, minWidth: 800, title: "Sign In",
              close: function(event, ui)
                  {
                      $("#emailSignIn").val("");
                      $("#pwdSignIn").val("");
                  }
              }
          );

          // putting #enrollDialog->close in the #signInDialog definition did not work (even though it does work
          // in the #enrollDialog definition), hence we use the command below which does work
          $("#signInDialog").on("dialogopen", function(event, ui)
              {
                  $("#enrollDialog").dialog("close")
              }
          );

          $("#messageDialog").dialog(
              {
              title: "Message", modal: true, autoOpen: false, minWidth: 400, position:
                  {
                  my: "center", at: "center", of: window
                  }
                  ,
              buttons:
                  {
                  "Close": function()
                      {
                          $(this).dialog("close");
                      }
                  }
              }
          );

          $("#confirmDialogForInsert").dialog(
              {
              autoOpen: false, minWidth: 400, modal: true, title: "Register",
              buttons:
                  {
                  "Yes": function()
                      {
                          confirm_ret = true;
                          $(this).dialog("close");
                          do_insert();
                      }
                      ,
                  "No": function()
                      {
                          confirm_ret = false;
                          $(this).dialog("close");
                      }
                  }
              }
          )

          $("#confirmDialogForRemove").dialog(
              {
              autoOpen: false, minWidth: 400, modal: true, title: "Remove",
              buttons:
                  {
                  "Yes": function()
                      {
                          confirm_ret = true;
                          $(this).dialog("close");
                          do_remove();
                      }
                      ,
                  "No": function()
                      {
                          confirm_ret = false;
                          $(this).dialog("close");
                      }
                  }
              }
          )

          $("#forgotPasswordDialog").dialog(
              {
              modal: true, autoOpen: false, minWidth: 800, title: "Reset Password",
              close: function(event, ui)
                  {
                      $("#forgotPasswordResultMsg").html("");
                  }
                  ,
              open: function(event, ui)
                  {
                      $("#forgotPasswordEmailInput").val("");
                      $("#forgotPasswordResultMsg").html("");
                  }
              }
          );

          $("#forgotPasswordTrigger").click(function()
              {
                  $("#signInDialog").dialog("close");
                  $("#ajaxLoaderGif").hide(1);
                  $("#forgotPasswordDialog").dialog("open");
              }
          );

          $("#signInTrigger").click(function()
              {
                  $("#signInDialog").dialog(
                      {
                      modal: true, open: function(event, ui)
                          {
                              $("#signInResultNG").html("");
                              $("#pwdSignIn").val("");
                          }
                      }
                  );
                  $("#signInDialog").dialog("open");

              }
          );

          $("#swapDialog").dialog(
              {
              modal: true, autoOpen: false, minWidth: 600, minHeight: 500, closeText: "Cancel", title: "Swap Timeslots",
              buttons:
                  {
                  "Submit": function()
                      {
                          $("#swapForm").submit();
                      },
                  "Close": function()
                      {
                          $(this).dialog("close");
                      }
                  }

              }
          );
      };

      function initializeToolTips()
      {
          //$('.tooltip').tooltipster();  // get ready to use tooltips
          $('#welcomeEnrollHelp').tooltipster(
              {
              content: $('<span><strong>Click this button to enroll</strong><br/><hr/>You only need to enroll once.</span>')
              }
          );
          $('#welcomeSignInHelp').tooltipster(
              {
              content: $('<span><strong>Click this button to sign in (after enrolling)</strong><br/><hr/>You must sign in with your email and password</span>')
              }
          );
          $('#bellringingCalendar').tooltipster(
              {
              content: $('<span><strong>Click this link to refresh the calendar</strong><br/><hr/>You&lsquo;ll' +
                      ' want to do this if the calendar has been left open for a<br/>' +
                      'while to check to see if somebody else has selected a timeslot </span>')
              }
          );
          $('#signOutTrigger').tooltipster(
              {
              content: $('<span><string>Click this link to sign out</strong><br/><hr/>Signing out is not necessary.' +
                      '<br/>You can just close your browser.<br/>However if somebody else in your family ' +
                      '<br/>wants to sign in then use this link.</span>')
              }
          );
          $('#allDaysSpan').tooltipster(
              {
              content: $('<span><strong>Check this checkbox to show all days in the project</strong><br/><hr/>' +
                      'By default the calendar only displays current and future days.<br/>' +
                      'By checking this box and clicking on the &ldquo;Refresh calendar&rdquo; link' +
                      '<br/>all days will be displayed. However you can only change schedules<br/>' +
                      'for the current and future days.</span>')
              }
          );
          $('#signInInfoImg').tooltipster(
              {
              content: $('<span><strong>Sign In</strong><br/><hr/>' +
                      'Enter your email address and your password<br/>' +
                      'and click on the &ldquo;Sign In&rdquo;<br/>' +
                      'button. If you forgot your password<br/>' +
                      'click on the &ldquo;Forgot Password&rdquo;' +
                      'link.</span>')
              }
          );
          $('#forgotPasswordInfo').tooltipster(
              {
              content: $('<span><strong>Forgot Password</strong><br/><hr/>' +
                      'Enter your email address and ' +
                      'click on the &ldquo;Reset Password&rdquo;<br/>' +
                      'button. You will be sent an email with a link<br/>' +
                      'to set a new password (valid for one hour).</span>')
              }
          );
          $('#generalInfo').tooltipster(
              {
              content: $('<span><strong>How to Schedule a Timeslot</strong><br/><hr/>' +
                      'After signing in a table of timeslots will be<br/>' +
                      'displayed. Click on an empty timeslot and confirm that<br/>' +
                      'you want to select this slot. Your name will appear<br/>' +
                      'in that position.<br/><br/>' +
                      'If you want to remove your name from a scheduled<br/>' +
                      'time, click on your name and confirm your action.<br/><br/>' +
                      'Note that you can&acute;t select a timeslot if somebody<br/>' +
                      'else has selected it nor can you select a timeslot if<br/>' +
                      'you have already selected a timeslot at the same time in<br/>' +
                      'another location.<br/><br/>' +
                     // 'If you wish to swap schedules with a person, click on<br/>' +
                     // 'the <img src="images/swap_icon.png" height="25px" width="25px" alt="" /> icon&nbsp;' +
                     // 'next to that person and then select<br/>' +
                     // 'your timeslot you wish to swap.<br/><br/>' +
                      'If the schedule display has been opened for a while<br/>' +
                      'it&acute;s a good idea to refresh the calendar to make sure<br/>' +
                      'that nobody has selected a timeslot while you&acute;ve<br/>' +
                      'had the table opened.</span>')
              }
          );
          $('#enrollInfo').tooltipster(
              {
              content: $('<span><strong>Enrolling</strong><br/><hr/>' +
                      'All fields are required<br/>' +
                      '<ul>' +
                      '<li>First &amp; Last Name - Display purposes only</li>' +
                      '<li>Email - Will be used as login id and to send reminders</li>' +
                      '<li> Password - must be six or more characters with<br/>at least one letter and one number</li>' +
                      '<li>Passwords are CASE SENSITIVE</li>' +
                      '</ul>' +
                      '<br/>' +
                      'You will be asked to confirm both your email and password<br/>' +
                      'in the boxes below &ldquo;Email&rdquo; and &ldquo;Password&rdquo;<br/>' +
                      '</span>')
              }
          );

          $('#forgotPasswordTrigger').tooltipster(
              {
              content: $('<span><strong>Password Reset</strong><br/><hr/>' +
                      'If you forget your password you may<br/>' +
                      'reset it by clicking on this &ldquo;Forgot<br/>' +
                      'Password&rdquo; link. Enter your email address<br/>' +
                      'and you will receive a link to set a new password<br/>' +
                      '(valid for one hour). On localhost the link is<br/>' +
                      'shown in the dialog instead of email.' +
                      '</span>')
              }
          );

          $('#enrollPwdInfo ').tooltipster(
              {
              content: $('<span><strong>Password Requirements</strong><br/><hr/>' +
                      '<ul>' +
                      '<li>Must be a minimum of six (6) characters</li>' +
                      '<li>May not contain spaces</li>' +
                      '<li>Must have at least one (1) letter<br/>and one (1) number</li>' +
                      '<li>Passwords are CASE SENSITIVE</li>' +
                      '</ul>' +
                      '</span>')
              }
          )
      }

      function getSchedulePusherSocketId()
      {
          if (schedulePusher && schedulePusher.connection && schedulePusher.connection.socket_id) {
              return schedulePusher.connection.socket_id;
          }
          return "";
      }

      function connectSchedulePusher()
      {
          if (!PUSHER_ENABLED || typeof Pusher === "undefined") {
              return;
          }
          disconnectSchedulePusher();
          schedulePusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
          schedulePusherChannel = schedulePusher.subscribe(PUSHER_SCHEDULE_CHANNEL);
          schedulePusherChannel.bind(PUSHER_SCHEDULE_EVENT, onScheduleChanged);
      }

      function disconnectSchedulePusher()
      {
          if (schedulePusherChannel) {
              schedulePusherChannel.unbind(PUSHER_SCHEDULE_EVENT, onScheduleChanged);
              schedulePusher.unsubscribe(PUSHER_SCHEDULE_CHANNEL);
              schedulePusherChannel = null;
          }
          if (schedulePusher) {
              schedulePusher.disconnect();
              schedulePusher = null;
          }
      }

      var scheduleToastTimer = null;

      function showScheduleToast(message)
      {
          var text = message || "Another user has just updated their schedule";
          var container = document.getElementById("schedule-toast-container");
          if (!container) {
              container = document.createElement("div");
              container.id = "schedule-toast-container";
              document.body.appendChild(container);
          }
          var toast = document.createElement("div");
          toast.className = "schedule-toast";
          toast.textContent = text;
          container.innerHTML = "";
          container.appendChild(toast);
          if (scheduleToastTimer) {
              clearTimeout(scheduleToastTimer);
              scheduleToastTimer = null;
          }
          requestAnimationFrame(function() {
              toast.classList.add("show");
          });
          scheduleToastTimer = setTimeout(function() {
              toast.classList.remove("show");
              scheduleToastTimer = setTimeout(function() {
                  if (toast.parentNode) {
                      toast.parentNode.removeChild(toast);
                  }
              }, 250);
          }, 3500);
      }

      function onScheduleChanged(data)
      {
          var payload = data;
          if (typeof data === "string") {
              try {
                  payload = JSON.parse(data);
              } catch (e) {
                  return;
              }
          }
          showScheduleToast("Another user has just updated their schedule");
          if (!updateCalendarSlot(payload.oldCode, payload.newCode, payload.name || "")) {
              showCalendar();
          }
      }

      function signOut()
      {
          disconnectSchedulePusher();
          $("#signOutTrigger").hide(1);
          $("#signInInfo").text("");
          $("#bellringingCalendar").hide(1);
          $("#allDaysSpan").hide(1);
          $("#calendarDiv").html("");
          $("#accordion").accordion(
              {
              active: false
              }
          );
          $("#signInFirstWarning").show(1);
          $("#showSchedule").hide(1);
          $("body").removeClass("logged-in").addClass("logged-out");
      }

      function setAccordion() // Sets up the two accordion panes
      {
          $("#accordion").accordion(
              {
              collapsible: true,
              active: false,
              heightStyle: "content",
              animate: false,
              active: 0
              }
          );
      }

      function setSignInValidate() // jQuery validation for the "Sign In" form
      {
          $("#signInForm").validate(
              {
              rules:
                  {
                  emailSignIn:
                      {
                      required: true,
                      email: true
                      }
                      ,
                  pwdSignIn:
                      {
                      required: true
                      }
                  }
                  ,
              errorPlacement: function(error, element)
                  {
                      placeValidationError(error, element);
                  }
                  ,
              submitHandler: function(form)
                  {
                      var formData = $("#signInForm").serialize();
                      $.post("signin.php", formData, signInResult); //calls the signInResult() function after success
                  }
              }
          )
      };


      function setRegisterValidate() // jQuery validation for the "Registration" form
      {
          $("#registerForm").validate(
              {
              rules:
                  {
                  fname:
                      {
                      required: true
                      }
                      ,
                  lname:
                      {
                      required: true
                      }
                      ,
                  email:
                      {
                      required: true,
                      email: true
                      }
                      ,
                  email2:
                      {
                      equalTo: "#email"
                      }
                      ,
                  phone:
                      {
                      required: true,
                      phoneUS: true
                      }
                      ,
                  pwd:
                      {
                      required: true,
                      minlength: 6,
                      nowhitespace: true,
                      pattern: /^(?=.*\d)(?=.*[A-Za-z]).{6,}$/
                      }
                      ,
                  pwd2:
                      {
                      equalTo: "#pwd"
                      }
                  }
                  ,
              messages:
                  {
                  pwd:
                      {
                      pattern: "Password must at least 6 characters with at least one letter and one digit"
                      }
                  }
                  ,

              errorPlacement: function(error, element)
                  {
                      placeValidationError(error, element);
                  }
                  ,
              submitHandler: function(form)
                  {
                      // form.submit();
                      var formData = $("#registerForm").serialize();
                      $.post("register.php", formData, registerResult); // Calls the registerResult() function after success
                  }
              }
          )
      };


      function registerResult(data, status)
      {
          if (data.substr(0, 1) == 'R') // register.php returns a message starting with "R" on success
          {
              $("#enrollDialog").dialog("close");
              $("#messageDialog").html("Registration Succeeded");
              $("#messageDialog").dialog("open");
          }
          else
          {
              $("#registerResult").html('<p style="color:red;">' + data + '<p>'); // e.g. email address already in use
          }
      }

      function placeValidationError(error, element)
      {
          var wrapper = element.closest(".pwd-input-wrap, .enroll-input-wrap");
          if (wrapper.length) {
              error.insertAfter(wrapper);
          } else {
              error.insertAfter(element);
          }
      }

      function setForgotPasswordValidate()
      {
          $("#forgotPasswordForm").validate(
              {
              rules:
                  {
                  forgotPasswordEmailInput:
                      {
                      required: true,
                      email: true
                      }
                  }
                  ,
              errorPlacement: function(error, element)
                  {
                      error.insertAfter(element);
                  }
                  ,
              submitHandler: function(form)
                  {
                      $("#forgotPasswordResultMsg").html("");
                      var formData = $("#forgotPasswordForm").serialize();
                      $.post("forgot_password_request.php", formData, forgotPasswordResult);
                      $("#ajaxLoaderGif").show(1);
                  }
              }
          )
      };


      function forgotPasswordResult(data, status)
      {
          $("#ajaxLoaderGif").hide(1);
          var payload = data;
          if (typeof payload === "string") {
              try {
                  payload = JSON.parse(payload);
              } catch (e) {
                  payload = {ok: false, error: "Unexpected response from reset request."};
              }
          }

          if (!payload || payload.ok !== true) {
              var err = payload && payload.error ? payload.error : "Could not process reset request.";
              $("#forgotPasswordResultMsg").html('<span style="color:red;">' + err + '</span>');
              return;
          }

          if (payload.dev_reset_url) {
              var safeUrl = $('<div/>').text(payload.dev_reset_url).html();
              var msg = payload.message ? payload.message : "Open the reset link below.";
              $("#forgotPasswordResultMsg").html(
                  '<div style="color:#0b5ed7;">' + msg + '</div>' +
                  '<div style="margin-top:10px;"><a href="' + safeUrl + '">' + safeUrl + '</a></div>'
              );
          } else {
              var okMsg = payload.message ? payload.message : "If an account exists for that email, you will receive password reset instructions shortly.";
              $("#forgotPasswordResultMsg").html(
                  '<div style="color:#0b5ed7;">' + okMsg + '</div>' +
                  '<div style="color:#CC0000; margin-top:10px; font-size:small;">Please close this window when you are finished here. The reset link in your email will open the password reset page in a new window.</div>'
              );
          }
      }

      function parseSignInResponse(data)
      {
          if (typeof data === "object" && data !== null) {
              return data;
          }
          if (typeof data !== "string") {
              return null;
          }
          var trimmed = data.trim();
          if (trimmed.indexOf("Invalid") === 0) {
              return null;
          }
          var jsonStart = trimmed.indexOf("{");
          if (jsonStart === -1) {
              return null;
          }
          var payload = trimmed.substring(jsonStart);
          try {
              return JSON.parse(payload);
          } catch (e) {
              try {
                  return eval("(" + payload + ")");
              } catch (e2) {
                  return null;
              }
          }
      }

      function signInResult(data, status)
      {
          var rg = parseSignInResponse(data);
          if (rg && rg.Ringer && rg.Ringer[0])
          {
              // Sign in was good so:
              // - close the dialog
              // - show name and email on top of page
              // - put the user id in the "rgr" div for later use in showCalander()
              // - Hide the "Sign in first" warning
              // - show the "Show Calander link on the top of the page
              $("#signInDialog").dialog("close");
              var admin;
              if (rg.Ringer[0].administrator == "1")
                  admin = "A";
              else
                  admin = "";
              $("#admin").html(admin);
              $("#signInInfo").text("Signed in as " + rg.Ringer[0].email);
              $("#rgrid").attr("value", rg.Ringer[0].id);
              $("#bellringingCalendar").show(1);
              $("#allDaysSpan").show(1);
              $("#signInFirstWarning").hide(1);
              $("#signOutTrigger").show(1);
              $("#showSchedule").show(1);
              $("body").removeClass("logged-out").addClass("logged-in");
              showCalendar();
              connectSchedulePusher();
          }
          else if (typeof data === "string" && data.trim().indexOf("Invalid") === 0)
          {
              $("#signInResultNG").html(data);
          }
          else if (typeof data === "object" && data && data.error)
          {
              $("#signInResultNG").html(data.error);
          }
          else
          {
              $("#signInResultNG").html("Unexpected sign-in response. Please try again.");
          }
      }

      function showResetSuccessMessage()
      {
          var params = new URLSearchParams(window.location.search);
          if (params.get("reset") === "1") {
              $("#messageDialog").html("Password updated. Please sign in with your new password.");
              $("#messageDialog").dialog("open");
              params.delete("reset");
              var next = window.location.pathname;
              var nextQs = params.toString();
              if (nextQs) {
                  next += "?" + nextQs;
              }
              history.replaceState({}, document.title, next);
          }
      }

      function setShowCalendarTrigger() // when Show Calendar link is clicked call showCalander() function
      {
          $("#bellringingCalendar").click(function()
              {
                  showCalendar();
              }
          )
      }

      function showCalendar() // serialize data in the form with id = hiddenForm (contains the ringer id populated by signInResult()
      {
          // and passes it to the PHP script "showcalendar.php"
          var rgrid;
          if ($("#allDaysCheckBox").is(':checked'))
          {
              chk = "All";
          }
          else
          {
              chk = "None";
          }
          //rgrid = document.getElementById("rgrid").getAttribute("value"); // need javascript - jQuery doesn't work
          formData = $("#hiddenForm").serialize();
          formData = formData + "&allDays=" +chk;
          $.post("showcalendar.php", formData, printCalendar);
      }

      function setTipsterForCalendar()
      {
          $('.tableHelp').tooltipster(
              {
              content: $('<span><strong>Scheduling</strong><br/><hr/>' +
                      '<ul>' +
                      '<li>Click on an empty timeslot<br/>to schedule yourself for that slot.</li>' +
                      '<li>Click on a timeslot with your ' +
                      'name to remove<br/>yourself from the schedule.</li>' +
                   //   '<li>Click on the <img src="images/swap_icon.png" height="25px" width="25px" alt="" /> icon next to<br/>' +
                  //    'a person to swap with that person</li>'  +
                      '</span>')
              }
          )
      }

      function printCalendar(data, status) // called after success of showcalendar.php and will populate calendarDiv with results
      {
          $("#accordion").accordion(
              {
              active: 0
              }
          ); // open the first accordion panel
          $("#calendarDiv").html(data);
          $("#calendarTableId input").addClass("rounded2");
          $("#calendarTableId").addClass("calendarClass");
          $(".swapIcon").click(setSwapDialog);
          setTipsterForCalendar();
      }

      function updateCalendarSlot(oldCode, newCode, name)
      {
          var $label = $('label[code="' + oldCode + '"]');
          if ($label.length === 0) {
              return false;
          }
          $label.attr("id", newCode);
          $label.attr("code", newCode);
          if (name) {
              $label.text(name);
              $label.addClass("calendarTableLblRed");
          } else {
              $label.text("");
              $label.removeClass("calendarTableLblRed");
          }
          return true;
      }

      function applySlotResult(data, status)
      {
          var parts = data.split("#");
          if (parts[0] === "success" && parts.length >= 3) {
              var oldCode = $("#cd_str").html();
              if (!updateCalendarSlot(oldCode, parts[2], parts[3] || "")) {
                  showCalendar();
              }
              return;
          }
          if (parts[0] === "error") {
              showCalendar();
              $("#messageDialog").html(parts.slice(1).join("#"));
              $("#messageDialog").dialog("open");
              return;
          }
          showCalendar();
          $("#messageDialog").html(data);
          $("#messageDialog").dialog("open");
      }

      function setSwapDialog()
      {
          var str = $(this).attr("code");
          $.post("swap.php", "code=" + str, showSwapDialog);
      }

      function showSwapDialog(data, status)
      {
          str = data.split("@@@!!!@@@");
          $("#swapDialogTopMessage").html(str[0]);
          $("#swapFormDiv").html(str[1]);
          $("#swapDate").datepick(
              {
                  dateFormat: "D MM d, yyyy'",
                  minDate: 0,
                  autoSize: true,
                  showOptions: "",
                  showAnim: "",
                  changeMonth: true,
                  showTrigger: '<button type="button" class="trigger">' +
                         '<img src="images/calendar-blue.gif" alt="Select Date"></button>'
                /* minDate: new Date(), constrainInput: true, dateFormat: "M d, yy",
                 showOn: "button",
                 buttonImage: "images/calendar.gif",
                 buttonImageOnly: true,
                 buttonText: "Select date" */
              }
          );
          setSwapDialogValidation();
          $("#swapDialogBottomMessage").html("");
          $("#swapDialog").dialog("open");

      }

      function setSwapDialogValidation()
      {
          $("#swapForm").validate(
              {
              rules:
                  {
                  swapSlot:
                      {
                      required: true
                      }
                      ,
                  swapLocation:
                      {
                      required: true
                      }
                      ,
                  swapDate:
                      {
                      required: true,
                      date: true
                      }
                  },
              messages:
              {
                  swapSlot: "Please select a timeslot",
                  swapLocation: "Please select a location",
                  swapDate:
                  {
                     date: "Date is not valid",
                     required: "Please select a date"
                  }
              },
              errorContainer: "#errorContainer",
              errorLabelContainer: "#errorContainer",
              errorElement: "li",
              submitHandler: function() {submitSwapForm(); }
              }
          )
      }

      function submitSwapForm()
      {
         str = $("#swapForm").serialize();
         $.post("do_swap.php", str, processSwap);
      }

      function processSwap(data, status)
      {
          $("#swapDialogBottomMessage").html(data);
      }

      function processCalendarClick()
      {
          var str = $(this).attr("code");
          $.post("change_schedule.php", "code=" + str, test_result)
      }

      function test_result(data, status) // called when user clicks on a calander box. Appropriate code returned by ProcessCalendarClick
      {
          var rslt = data.split("#");
          var msg = rslt[1];
          var ret_cd = rslt[0];
          var cd_str = rslt[2];
          switch (ret_cd) // 0 = removal, 1 = slot occupied, 2 = allowable insert, 3 = user already in timeslot
          {
              // 4 = tried to schedule or change a day in the past, 5 = sql error
              case "0":
                  $("#cd_str").html(cd_str);
                  $("#confirmDialogForRemove").html(msg);
                  $("#confirmDialogForRemove").dialog("open");
                  break;

              case "1":
                  $("#messageDialog").html(msg);
                  $("#messageDialog").dialog("open");
                  break;

              case "2":
                  $("#cd_str").html(cd_str);
                  $("#confirmDialogForInsert").html(msg);
                  $("#confirmDialogForInsert").dialog("open");
                  /* if (confirm(msg) == true)
            $.ajax({
              type: 'POST',
              url: 'do_insert.php',
              data: "code=" + cd_str,
              success: insert_result,
              async: false
              });*/

                  break;

              case "3":
                  $("#messageDialog").html(msg);
                  $("#messageDialog").dialog("open");
                  break;

              case "4":
                  $("#messageDialog").html(msg);
                  $("#messageDialog").dialog("open");
                  break;

              case "5":
                  $("#messageDialog").html(msg);
                  $("#messageDialog").dialog("open");
                  break;
          }
      }

      function do_remove()
      {
          $.post("do_remove.php", {
              code: $("#cd_str").html(),
              socket_id: getSchedulePusherSocketId()
          }, remove_result);
      }

      function do_insert()
      {
          $.ajax(
              {
              type: 'POST',
              url: 'do_insert.php',
              data: {
                  code: $("#cd_str").html(),
                  socket_id: getSchedulePusherSocketId()
              },
              success: insert_result,
              async: false
              }
          );
      }

      function remove_result(data, status)
      {
          applySlotResult(data, status);
      }

      function insert_result(data, status)
      {
          applySlotResult(data, status);
      }

      function wirePasswordToggle(btnId, inputId) {
          var btn = document.getElementById(btnId);
          var input = document.getElementById(inputId);
          if (!btn || !input) {
              return;
          }
          btn.addEventListener("click", function() {
              var icon = btn.querySelector("i");
              var show = input.type === "password";
              input.type = show ? "text" : "password";
              if (icon) {
                  icon.className = show ? "bi bi-eye-slash" : "bi bi-eye";
              }
              btn.setAttribute("title", show ? "Hide password" : "Show password");
              btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
          });
      }

      function wirePasswordToggles() {
          wirePasswordToggle("toggleEnrollPwd", "pwd");
          wirePasswordToggle("toggleEnrollPwd2", "pwd2");
          wirePasswordToggle("toggleSignInPwd", "pwdSignIn");
      }


      </script>

</head>

<body class="logged-out">
    <div id="header">
      <a href="#" target="_self" id="signOutTrigger" style="display:none">Sign out&nbsp;&nbsp;<img src="images/Help-icon.png" alt="?" class="infoCursor" height="22" width="22" /></a>
      <a href="#" target="_self" id="bellringingCalendar" style="display: none;">Refresh calendar&nbsp;&nbsp;<img src="images/Help-icon.png" alt="?" class="infoCursor" height="22" width="22" /></a>
      <span id="allDaysSpan" class="infoCursor">All Dates&nbsp;&nbsp;<img src="images/Help-icon.png" alt="?" class="infoCursor" height="22" width="22" /><input type="checkbox" id="allDaysCheckBox" /></span>
      <span id="signInInfo"></span>
      <span class="header-spacer"></span>
      <span id="showSchedule"><button type="button" id="showScheduleButton">Show Full Schedule</button></span>
      <img src="images/Help-icon.png" alt="?" class="infoCursor" id="generalInfo" height="32" width="32" />
      <button type="button" id="helpButton">Help</button>
    </div>

    <a href="#" id="enrollTrigger" style="display:none" aria-hidden="true"></a>
    <a href="#" id="signInTrigger" style="display:none" aria-hidden="true"></a>

    <!-- The dialogs -->

    <!-- The registration (or enroll) diaglog -->
      <div id="enrollDialog">
        <form action="register.php" method="post" id="registerForm">

            <label for="fname">First Name: </label> <span class="enroll-input-wrap"><input type="text" id="fname" name="fname" size="30" maxlength="20" class="rounded2"/></span> <br/>
            <label for="lname">Last Name: </label> <span class="enroll-input-wrap"><input type="text" id="lname" name="lname" size="30" maxlength="30" class="rounded2"/></span> <br/>

            <label for="email">Email: </label> <span class="enroll-input-wrap"><input type="text" id="email" name="email" size="30" maxlength="50" class="rounded2"/></span> <br/>
            <label for="email2">Confirm email: </label> <span class="enroll-input-wrap"><input type="text" id="email2" name="email2" size="30" maxlength="50" class="rounded2"/></span> <br/>
            <label for="phone">Phone No: </label> <span class="enroll-input-wrap"><input type="text" id="phone" name="phone" size="20" maxlength="13" class="rounded2"/></span> <br/>
            <label style="font-size: small;">(###-###-####)</label> <br/> <br/>
            <label for="pwd">
                Password: <img src="images/Help-icon.png" alt="?" class="infoCursor" id="enrollPwdInfo" height="20px" width="20px"  alt="?" />
            </label>
            <div class="pwd-input-wrap enroll-pwd-wrap">
                <input type="password" id="pwd" name="pwd" size="30" maxlength="20" class="rounded2"/>
                <button type="button" class="pwd-toggle-inside" id="toggleEnrollPwd" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div> <br/>
            <label for="pwd2">Confirm password: </label>
            <div class="pwd-input-wrap enroll-pwd-wrap">
                <input type="password" id="pwd2" name="pwd2" size="30" maxlength="20" class="rounded2"/>
                <button type="button" class="pwd-toggle-inside" id="toggleEnrollPwd2" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
            <br/><br/>
            <input type="submit" name="register" value="Enroll"/>
            <img src="images/Help-icon.png" alt="?" class="infoCursor" id="enrollInfo" height="40px" width="40px" />

        </form>
        <div id="registerResult"></div>
      </div>


      <div id="signInDialog">
        <form action="signin.php" method="post id" id="signInForm">
            <label for="emailSignIn">Email: </label>
            <span class="enroll-input-wrap"><input type="text" id="emailSignIn" name="emailSignIn" size="30" maxlength="50" class="rounded2"/></span> <br/>
            <label for="pwdSignIn">Password: </label>
            <div class="pwd-input-wrap enroll-pwd-wrap">
                <input type="password" id="pwdSignIn" name="pwdSignIn" size="30" maxlength="20" class="rounded2" />
                <button type="button" class="pwd-toggle-inside" id="toggleSignInPwd" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div> <br/>
            <label for="cb_remember">Remember Me:</label>
            <input type="checkbox" id="cb_remember" name="cb_remember" /> <br/>
            <br/>
            <input type="submit" name="signIn" value="Sign In"/>&nbsp;&nbsp;
            <img src="images/Help-icon.png" id="signInInfoImg" alt="?" height="36" width="36" class="infoCursor" />
            <a href="#" id="forgotPasswordTrigger" target="_self">Forgot Password</a>
        </form>
        <div style="color: red" id="signInResultNG"></div>
      </div>

      <div id="forgotPasswordDialog">  <!-- displays after you've clicked on the "Forgot Password" link in the Sign In dialog -->
          <form action="forgot_password_request.php" id="forgotPasswordForm" method="post">
              <img src="images/Help-icon.png" id="forgotPasswordInfo" alt="?" height="40px" width="40px" class="infoCursor" style="float: left"/>
              <label for="forgotPasswordEmailInput" style="float: left">Enter Email: </label>
              <input type="text" id="forgotPasswordEmailInput" name="forgotPasswordEmailInput"/><br/><br/>
              <input type="submit" value="Reset Password"/> <br/><br/>
          </form>
          <div id="forgotPasswordResultMsg"></div>
          <img id="ajaxLoaderGif" src="images/ajax-loader.gif" alt="Wait" />
      </div>

      <div id="swapDialog">
          <div id="swapDialogTopMessage"></div>
          <hr/>
          <div id="swapFormDiv"></div>
          <hr/>
          <div id="swapDialogBottomMessage"></div>
      </div>

      <div id="signInResultOK"></div>
      <div id="messageDialog"></div>
      <div id="confirmDialogForInsert"></div>
      <div id="confirmDialogForRemove"></div>
      <div id="cd_str" style="display: none";></div>

  <main class="page-main">
      <header class="page-hero">
          <img src="mhk_std_logo_transparent 640.png" alt="Monument Hill Kiwanis logo" />
          <h1><span id="bellringingYear"></span> Salvation Army Bellringing Signup</h1>
      </header>

      <div id="signInFirstWarning" class="welcome-card">
           <h3>Instructions to sign up for a Bell Ringing Slot</h3>
           <ul>
               <li>Enroll (you only need to do this once) — click the <strong>Enroll</strong> button below</li>
               <li>After that, sign in with the <strong>Sign In</strong> button below</li>
               <li>If you need help at any time, hover over the <img src="images/Help-icon.png" alt="?" height="22" width="22"/> icon next to a button or elsewhere on the page</li>
               <li>After signing in, click an empty time slot to schedule yourself; click your name again to remove yourself</li>
               <li>Questions? Email Jeff Baker at <a href="mailto:wjeffreybaker@gmail.com">wjeffreybaker@gmail.com</a></li>
               <li>Login or app problems? Email Mark Zeiger at <a href="mailto:mark.zeiger@gmail.com">mark.zeiger@gmail.com</a></li>
           </ul>
           <p class="welcome-actions">
               <span class="welcome-action-item">
                   <a href="#" class="welcome-btn welcome-btn-primary" id="welcomeEnroll">Enroll</a>
                   <img src="images/Help-icon.png" alt="?" class="infoCursor" id="welcomeEnrollHelp" height="22" width="22" />
               </span>
               <span class="welcome-action-item">
                   <a href="#" class="welcome-btn welcome-btn-secondary" id="welcomeSignIn">Sign In</a>
                   <img src="images/Help-icon.png" alt="?" class="infoCursor" id="welcomeSignInHelp" height="22" width="22" />
               </span>
           </p>
      </div>

      <div id="calendarDiv"></div>
      <form id="hiddenForm">
        <input type="hidden" id="rgrid" value="" name="rgrid">
      </form>
      <div style="display:none;" id="admin"></div>
  </main>

</body>
</html>