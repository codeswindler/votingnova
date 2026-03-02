-- Cleanup Script: Remove Pending Votes
-- This script removes all pending vote records from the database
-- Since we now only create votes when payment is confirmed, pending votes are no longer needed

-- WARNING: This will permanently delete pending vote records
-- Make sure you have a backup before running this!

-- Option 1: Delete all pending votes
DELETE FROM votes WHERE status = 'pending';

-- Option 2: Delete pending votes but keep failed/cancelled for audit (recommended)
-- DELETE FROM votes WHERE status = 'pending';

-- Option 3: Update pending votes to 'cancelled' instead of deleting (for audit trail)
-- UPDATE votes SET status = 'cancelled' WHERE status = 'pending';

-- Verify deletion (run this first to see what will be deleted)
-- SELECT COUNT(*) as pending_count FROM votes WHERE status = 'pending';
-- SELECT * FROM votes WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10;

-- After cleanup, verify no pending votes remain
-- SELECT COUNT(*) as remaining_pending FROM votes WHERE status = 'pending';
