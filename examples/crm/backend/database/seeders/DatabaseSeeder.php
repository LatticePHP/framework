<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Lattice\Auth\Models\Workspace;

final class DatabaseSeeder
{
    /**
     * Seed the CRM database with realistic test data.
     *
     * Creates: 3 users, 1 workspace, 20 companies, 50 contacts,
     * 30 deals across pipeline stages, 40 activities, 25 notes.
     */
    public function run(): void
    {
        // Create 3 users
        $admin = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $manager = User::create([
            'name' => 'Bob Manager',
            'email' => 'bob@example.com',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $rep = User::create([
            'name' => 'Carol Rep',
            'email' => 'carol@example.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        $users = [$admin, $manager, $rep];

        // Create 1 workspace
        $workspace = Workspace::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm',
            'owner_id' => $admin->id,
            'settings' => ['timezone' => 'UTC', 'currency' => 'USD'],
        ]);

        // Add all users as workspace members
        foreach ($users as $index => $user) {
            Capsule::table('workspace_members')->insert([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => $index === 0 ? 'owner' : ($index === 1 ? 'admin' : 'member'),
                'joined_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
        }

        // Create 20 companies
        $companies = [];
        $companyNames = [
            'TechCorp Solutions', 'FinanceHub Inc', 'MedTech Systems', 'CloudNine Software',
            'GreenLeaf Consulting', 'DataStream Analytics', 'BlueSky Innovations', 'PrimeLogic Ltd',
            'NovaTech Industries', 'Apex Digital', 'Quantum Computing Co', 'SwiftStack Technologies',
            'Meridian Group', 'Atlas Enterprises', 'Zenith Solutions', 'Pinnacle Systems',
            'Vanguard Tech', 'Stellar Dynamics', 'Horizon Labs', 'Nexus Corp',
        ];

        $industries = ['technology', 'finance', 'healthcare', 'manufacturing', 'retail', 'education', 'consulting'];
        $sizes = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1001+'];
        $countries = ['United States', 'Canada', 'United Kingdom', 'Germany', 'Australia'];

        foreach ($companyNames as $i => $name) {
            $companies[] = Company::create([
                'name' => $name,
                'domain' => strtolower(str_replace([' ', '.'], ['', ''], $name)) . '.com',
                'industry' => $industries[array_rand($industries)],
                'size' => $sizes[array_rand($sizes)],
                'phone' => '+1-555-' . str_pad((string) ($i * 100 + 1000), 4, '0', STR_PAD_LEFT),
                'email' => 'info@' . strtolower(str_replace([' ', '.'], ['', ''], $name)) . '.com',
                'address' => ($i * 100 + 100) . ' Business Ave',
                'city' => ['New York', 'San Francisco', 'Chicago', 'Austin', 'Seattle'][$i % 5],
                'state' => ['NY', 'CA', 'IL', 'TX', 'WA'][$i % 5],
                'country' => $countries[$i % 5],
                'website' => 'https://' . strtolower(str_replace([' ', '.'], ['', ''], $name)) . '.com',
                'owner_id' => $users[array_rand($users)]->id,
                'workspace_id' => $workspace->id,
            ]);
        }

        // Create 50 contacts
        $contacts = [];
        $firstNames = [
            'James', 'Sarah', 'Michael', 'Emily', 'David', 'Jessica', 'Robert', 'Amanda',
            'William', 'Ashley', 'John', 'Megan', 'Daniel', 'Brittany', 'Thomas', 'Samantha',
            'Christopher', 'Jennifer', 'Matthew', 'Nicole', 'Andrew', 'Stephanie', 'Joseph', 'Lauren',
            'Mark', 'Rachel', 'Steven', 'Heather', 'Brian', 'Elizabeth', 'Kevin', 'Michelle',
            'Jason', 'Amber', 'Timothy', 'Christina', 'Jeffrey', 'Kayla', 'Ryan', 'Melissa',
            'Eric', 'Tiffany', 'Patrick', 'Andrea', 'Nathan', 'Rebecca', 'Adam', 'Catherine',
            'Scott', 'Vanessa',
        ];
        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
            'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
            'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
            'Carter', 'Roberts',
        ];
        $statuses = ['lead', 'prospect', 'customer', 'churned', 'inactive'];
        $sources = ['web', 'referral', 'campaign', 'social', 'cold_call', 'trade_show'];
        $titles = ['CEO', 'CTO', 'VP Sales', 'Director of Engineering', 'Product Manager', 'Marketing Director', 'CFO', 'COO', 'Sales Manager', 'Account Executive'];

        for ($i = 0; $i < 50; $i++) {
            $firstName = $firstNames[$i];
            $lastName = $lastNames[$i];
            $contacts[] = Contact::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => strtolower($firstName . '.' . $lastName) . '@example.com',
                'phone' => '+1-555-' . str_pad((string) ($i * 10 + 100), 4, '0', STR_PAD_LEFT),
                'company_id' => $companies[array_rand($companies)]->id,
                'title' => $titles[array_rand($titles)],
                'status' => $statuses[array_rand($statuses)],
                'source' => $sources[array_rand($sources)],
                'owner_id' => $users[array_rand($users)]->id,
                'workspace_id' => $workspace->id,
                'tags' => json_encode(array_slice(['vip', 'enterprise', 'smb', 'startup', 'partner'], 0, rand(0, 3))),
            ]);
        }

        // Create 30 deals across pipeline stages
        $deals = [];
        $dealTitles = [
            'Enterprise License', 'Annual Subscription', 'Consulting Engagement', 'Platform Migration',
            'Custom Integration', 'Training Package', 'Support Contract', 'Data Analytics Suite',
            'Cloud Infrastructure', 'Security Audit', 'API Development', 'Mobile App Build',
            'SaaS Implementation', 'DevOps Transformation', 'AI/ML Pipeline', 'Digital Transformation',
            'ERP Integration', 'CRM Deployment', 'Marketing Automation', 'Sales Enablement',
            'Supply Chain Optimization', 'Customer Portal', 'IoT Platform', 'Blockchain PoC',
            'Cybersecurity Package', 'Compliance Audit', 'Staff Augmentation', 'Managed Services',
            'Performance Optimization', 'Infrastructure Overhaul',
        ];
        $stages = Deal::STAGES;

        for ($i = 0; $i < 30; $i++) {
            $stage = $stages[$i % count($stages)];
            $probability = match ($stage) {
                'lead' => 10,
                'qualified' => 25,
                'proposal' => 50,
                'negotiation' => 75,
                'closed_won' => 100,
                'closed_lost' => 0,
            };

            $deals[] = Deal::create([
                'title' => $dealTitles[$i],
                'value' => round(rand(5000, 500000) / 100, 2) * 100,
                'currency' => 'USD',
                'stage' => $stage,
                'probability' => $probability,
                'expected_close_date' => date('Y-m-d', strtotime('+' . rand(7, 180) . ' days')),
                'actual_close_date' => in_array($stage, ['closed_won', 'closed_lost'], true)
                    ? date('Y-m-d', strtotime('-' . rand(1, 90) . ' days'))
                    : null,
                'contact_id' => $contacts[array_rand($contacts)]->id,
                'company_id' => $companies[array_rand($companies)]->id,
                'owner_id' => $users[array_rand($users)]->id,
                'workspace_id' => $workspace->id,
                'lost_reason' => $stage === 'closed_lost'
                    ? ['Budget constraints', 'Chose competitor', 'Project postponed', 'Requirements changed'][rand(0, 3)]
                    : null,
            ]);
        }

        // Create 40 activities
        $activityTypes = Activity::TYPES;
        $activitySubjects = [
            'Initial discovery call', 'Follow-up on proposal', 'Product demo scheduled',
            'Contract review meeting', 'Quarterly business review', 'Technical requirements gathering',
            'Pricing discussion', 'Executive presentation', 'Negotiate terms', 'Send contract',
            'Onboarding kickoff', 'Training session', 'Support escalation', 'Account review',
            'Renewal discussion', 'Upsell opportunity', 'Reference check', 'Site visit',
            'Partnership meeting', 'Board presentation', 'Strategy session', 'Budget approval',
            'Legal review', 'Integration planning', 'Security assessment', 'Performance review',
            'Feature request follow-up', 'Complaint resolution', 'Thank you note', 'Birthday reminder',
            'Conference follow-up', 'Webinar invitation', 'Case study interview', 'Referral request',
            'Product feedback call', 'Competitive analysis share', 'ROI presentation', 'Pilot review',
            'Go-live planning', 'Post-implementation review',
        ];

        for ($i = 0; $i < 40; $i++) {
            $isCompleted = $i < 15;
            $dueOffset = $isCompleted ? '-' . rand(1, 30) . ' days' : '+' . rand(1, 30) . ' days';

            Activity::create([
                'type' => $activityTypes[array_rand($activityTypes)],
                'subject' => $activitySubjects[$i],
                'description' => 'Details for: ' . $activitySubjects[$i],
                'due_date' => date('Y-m-d H:i:s', strtotime($dueOffset)),
                'completed_at' => $isCompleted ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 15) . ' days')) : null,
                'contact_id' => $contacts[array_rand($contacts)]->id,
                'deal_id' => rand(0, 1) ? $deals[array_rand($deals)]->id : null,
                'owner_id' => $users[array_rand($users)]->id,
                'workspace_id' => $workspace->id,
            ]);
        }

        // Create 25 notes
        $noteContents = [
            'Had a great conversation about their needs. Very interested in our enterprise package.',
            'Budget approved! Moving forward with the proposal this week.',
            'Competitor X is also in the running. Need to differentiate on support quality.',
            'Key decision maker is on vacation until next Monday. Will follow up then.',
            'Technical team completed the evaluation. Positive feedback overall.',
            'Pricing concern raised. May need to offer volume discount.',
            'Mentioned they have a strict compliance requirement we need to address.',
            'Very impressed with the demo. Wants to schedule a pilot.',
            'Internal champion is pushing hard for our solution.',
            'Need to provide customer references from their industry.',
            'Contract is with legal for review. Expected turnaround: 2 weeks.',
            'Integration requirements are more complex than initially thought.',
            'Executive sponsor changed. Need to rebuild relationship.',
            'Successful pilot! 98% satisfaction rating from users.',
            'Renewal conversation went well. Discussing expansion.',
            'Feature request: they need SSO and SAML support.',
            'Competitive bake-off scheduled for next month.',
            'Training completed. Users are ramping up quickly.',
            'Quarterly review showed 3x ROI. Great case study potential.',
            'New project discussed during annual review. Potential $200k deal.',
            'Support ticket resolved. Customer very happy with response time.',
            'Billing issue clarified. Auto-payment set up for renewals.',
            'Attended their company event. Good networking opportunity.',
            'Shared our product roadmap. Aligned with their 2024 priorities.',
            'NPS score: 9/10. Will ask for G2 review.',
        ];

        $notableTypes = [Contact::class, Company::class, Deal::class];

        for ($i = 0; $i < 25; $i++) {
            $notableType = $notableTypes[$i % 3];
            $notable = match ($notableType) {
                Contact::class => $contacts[array_rand($contacts)],
                Company::class => $companies[array_rand($companies)],
                Deal::class => $deals[array_rand($deals)],
            };

            Note::create([
                'content' => $noteContents[$i],
                'notable_type' => $notableType,
                'notable_id' => $notable->id,
                'author_id' => $users[array_rand($users)]->id,
                'workspace_id' => $workspace->id,
                'is_pinned' => $i < 5,
            ]);
        }
    }
}
