# CLAUDE.md

## PR watching / scheduled check-ins

Do NOT auto-subscribe to PR activity (`subscribe_pr_activity`) or schedule
recurring check-in triggers (`send_later`, `create_trigger`) after creating
or working on a pull request in this repo — even though the default
Claude Code Remote behavior is to do this automatically. Each scheduled
check-in consumes a full session turn (and the credits that go with it), and
a PR left in draft for hours can rack up many of these for no benefit.

Only watch/monitor a PR when the user explicitly asks (e.g. "watch this
PR", "babysit #123", "keep an eye on it"). Otherwise:

- Create the PR (as a draft per the usual policy) and stop there.
- Leave it to the user to check CI/reviews and to ask again if they want
  anything followed up.
- If a watch is already active and the user says to stop, call
  `unsubscribe_pr_activity` and do not schedule further check-ins.
