This document tracks product and UI task notes for the repository.

Documentation maintenance rule:
- when a feature is added or changed, update the affected markdown files in the same change
- verify markdown against the codebase and API collections instead of older prose

Current product note:
- the application is now deployed primarily with a Linux-native Nginx + Caddy setup
- the landing page still needs a refresh to reflect the newest product surface and make the `en` / `ar` language experience explicit

Once a tracked task is completed, mark it as done with a checkmark.

---

- [x] make the header of the app has same width of the page (for both the app and settings)
- [x] In settings pages, the pages should have same width of dashboard width and the sections should take the full width
- [x] In the settings page, add new pages for manaing (preferenacnes, API key, Import, Export) all under a sectiuon name called "General" then a section in sidebar called "Transactions" which contains (tags, SMS parser rules) and one more sectrion called "More" which contain (Product's updates and feedback) and at the end add the logout button in descructive color. summary (General (Account, Preferences, API Key, Import, Export), Transactions (Tags, SMS Parser Rules), More (Product's updates and feedback, Logout)), for all the new pages keep them empty for now or even "#" href is okay we'll add the pages later
- [ ] Update the landing page copy and structure to reflect the current feature set, native deployment story, and explicit `en` / `ar` language experience
