# SELG Ballot Scanner Development Plan

## Phase 1: Foundation and Database Setup
- [x] Create project folder structure
- [x] Configure `.env` with MySQL placeholders
- [x] Add migrations for `users`, `positions`, `candidates`, `ballots`, `votes`
- [x] Add Eloquent models for all core entities
- [x] Add seeders for users, positions, and candidates

## Phase 2: Authentication and Admin Core
- [x] Install auth scaffolding (Laravel Breeze)
- [x] Add adviser-only middleware
- [x] Build CRUD for positions and candidates
- [x] Build adviser-only user account CRUD

## Phase 3: Physical Ballot Design
- [ ] Finalize ballot paper format (A4/Letter)
- [ ] Add 4 corner anchor markers
- [ ] Lock bubble size and spacing

## Phase 4: Scanning Engine (Python / FastAPI)
- [ ] Build image upload endpoint
- [ ] Implement marker detection and perspective warp
- [ ] Implement bubble detection by configured coordinates
- [ ] Return detected candidate IDs as JSON

## Phase 5: Camera Integration and Facilitator View
- [ ] Build scanner Blade page
- [ ] Integrate mobile camera with `getUserMedia`
- [ ] Capture frame and forward to FastAPI through Laravel
- [ ] Add review and submit confirmation

## Phase 6: Real-Time Results and Dashboard
- [ ] Build tally dashboard
- [ ] Add Chart.js visualizations
- [ ] Add live updates with Laravel Reverb (optional)

## Phase 7: Testing and Deployment
- [ ] Validate camera access via HTTPS/localhost (Ngrok for local phone tests)
- [ ] Test edge cases (duplicates, low light, partial shading)
- [ ] Deploy Laravel, MySQL, and Python service
