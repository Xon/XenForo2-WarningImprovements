# Warning Improvements

A Collection of improvements to XF2's warning system.

- Warnings/Bans with time-based expiry are now be removed on the 1st visit after the expiry time, rather than needing to wait for the hourly cron task to run.
- Sortable warnings with categories
  - Drag & drop
  - Permissions per category
  - per-category warning actions, allowing warning actions to be only triggered from points in that category
- Updated front-end using smart select menu, or radio boxes
- User Criteria for warning points
- Allow users to view their own warnings, and find which posts where warned. 
- Sends an alert to a user when they receive a warning. (Defaults on, togglable)
- Allows the Custom Warning to be customized
- Copy Warning title/text automatically to the public warning
- Allow non-custom Warnings to have thier Titles edited, opt-in
- Optional ability to "unsticky" the Warn button on the warning dialog
- Allow the default content action to be set
- Control defaults for user notification
  - Alerts
  - Lock conversations by default
  - Send warning conversations by default
  - Allow invite into warning conversations by default
- Option to require a note when entering a warning
 - and enforce a minimum length
- Ability to see warning actions applied to an account from the front-end
  - users may see warning actions against thier account
  - automatically roll-up identical warning actions to show the latest expiry
  - per-group moderator permissions for editing/viewing all/disable summarization.
- Additional conversation substitution replaceable for the warning conversation.
  - points
  - warning_title
  - warning_link
- Option to force new conversation email to be sent on a warning conversation. 
  - Will send even if they are banned!
  - Always sends full conversation text.
  - This can ignore conversation privacy options.
- Automatically extend default warning expires based on warning point total thresholds
- Anonymise warnings and warning alerts as a particular user or as a generic 'Moderation Staff' (WarningStaff phrase).
  - Affects Alerts and Warnings.
  - Does NOT change conversations.
- Round up warning expiry time to the nearest hour to avoid confusion over delays caused by XenForo task system's hourly schedule.
- Option to log a warning summary to a thread. Phrase: Warning_Summary_Message, can use bbcode
- New Warning Action actions triggered for the last valid warning action:
  - Post a new thread. Phrases Warning_Thread_Message & Warning_Thread_Title, can use bbcode.
  - Reply to an existing thread. Phrases Warning_Thread_Message, can use bbcode.
 
New Permission to control if a user can see who warned them.
- View Warning Issuer.

New moderator permissions for viewing warning actions.
- View Warning Actions
- Edit Warning Actions
- Don't Summarize Warning Actions