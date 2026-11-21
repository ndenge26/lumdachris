# Agent management setup

1. For an existing database created from the previous schema, apply `migrations/20261121_agent_management.sql` once. A fresh database created from the updated `schema.sql` already includes these changes and should not run the migration. The migration adds listing type, bedroom and certificate-review fields, service-document review status, property images, and saved assistant enquiries.
2. Create an agent account in the `users` table. Generate the password hash with PHP `password_hash()` on a trusted local machine, then insert the account (replace the placeholder with that generated hash):

   ```sql
   INSERT INTO users (full_name, email, phone, password_hash, role)
   VALUES ('Agent Name', 'agent@example.com', NULL, '<LOCAL_PASSWORD_HASH>', 'agent');
   ```

   Never store the plain-text password in the database, source code, or a public password-hashing service.
3. Configure the database through `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` as used by `config.php`.
4. Open `admin.php` and sign in with that agent account. Agent access is checked on every management request.

Property title and due-diligence documents are stored outside the web root in `lumdachris-private-uploads` and can only be downloaded by a signed-in agent. Public property photos are stored in `property-media`; the application accepts only JPEG, PNG, and WebP image uploads. A certificate marked “Verified by agent” records the agent’s review and is not an official government verification.
