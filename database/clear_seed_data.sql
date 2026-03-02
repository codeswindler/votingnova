-- Clear Seed Data Script
-- Run this script before going live to remove all simulation/test data
-- This will keep categories and nominees but remove all votes and transactions

USE votingnova;

-- Clear all votes (simulation data)
DELETE FROM votes;

-- Clear all M-Pesa transactions (simulation data)
DELETE FROM mpesa_transactions;

-- Clear all USSD sessions (simulation data)
DELETE FROM ussd_sessions;

-- Reset nominee vote counts to zero
UPDATE nominees SET votes_count = 0;

-- Verify cleanup
SELECT 
    (SELECT COUNT(*) FROM votes) as remaining_votes,
    (SELECT COUNT(*) FROM mpesa_transactions) as remaining_transactions,
    (SELECT COUNT(*) FROM ussd_sessions) as remaining_sessions,
    (SELECT SUM(votes_count) FROM nominees) as total_nominee_votes;

-- Note: Categories and Nominees are kept as they are needed for the live system
