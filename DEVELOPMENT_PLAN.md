# SELG Ballot Scanner Development Plan
http://localhost:8000/health
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
- [x] Finalize ballot paper format (A4/Letter)
- [x] Add 4 corner anchor markers
- [x] Lock bubble size and spacing

## Phase 4: Scanning Engine (Python / FastAPI)
- [x] Build image upload endpoint
- [x] Implement marker detection and perspective warp
- [x] Implement bubble detection by configured coordinates
- [x] Return detected candidate IDs as JSON

## Phase 5: Camera Integration and Facilitator View
- [x] Build scanner Blade page
- [x] Integrate mobile camera with `getUserMedia`
- [x] Capture frame and forward to FastAPI through Laravel
- [x] Add review and submit confirmation
- [ ] Improve shade / vote detection reliability for ballot scanning

## Phase 6: Real-Time Results and Dashboard
- [x] Build tally dashboard
- [x] Add Chart.js visualizations
- [x] Add reports page where all the election results stored
- [ ] Add live updates with Laravel Reverb (optional)

## Phase 7: Testing and Deployment
- [ ] Validate camera access via HTTPS/localhost (Ngrok for local phone tests)
- [ ] Test edge cases (duplicates, low light, partial shading)
- [ ] Deploy Laravel, MySQL, and Python service
