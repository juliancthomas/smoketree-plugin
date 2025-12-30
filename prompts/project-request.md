I would like to build a wordpress plugin for Smoketree Swim and Recreation Club, my neighborhood's pool club. The site is running Wordpress 6.9, the shared host server is not managed by us and running PHP 8.0.30. Also I'm using ACF Pro, and **WP Mail SMTP.**

I want the plugin to be professional and something worthy and safe to be displayed publicly on my github account. Object oriented is fine. I have full access to the database. Refer to the best practices document attached.

The swimclub does not have much of a budget for the website.

Memberships are currently

- Household
  - 1 primary + 4 family members
  - Can add 3 extra members for \$50 each
  - Access to pool and all other amenities
- Duo
  - 1 primary + 1 family member
  - Access to pool and all other amenities
- Single
  - 1 primary
  - Access to pool and all other amenities
- Civic Membership
  - 1 primary
  - Only for voting purposes
  - No access to pool
  - Access to all other amenities

Here is what the plugin should accomplish:

- Member Management
  - The plugin should allow members to register at smoketree.com/register
  - The plugin should allow admins to CRUD member information
  - Add or remove Family Members from an account
    - Members with a "Household" or "Duo" membership can add a family member to their account for free. Household membership can add 4, duo can add 1.
  - Add or remove Extra Members from account
    - Extra members are members added by members with a "Household" membership. It cost \$50 each per extra member to add them.
  - Add Guest passes to a member's account
  - Admin can export all members to a CSV, or filter and then export
- Membership Management
  - The plugin should handle the CRUD operations of the smoketree memberships. The admin should be able to edit the following of a membership:
    - Price
    - Expiration
    - Name
    - Description
    - Stripe Product ID
    - Is Selectable - Is this membership/product selectable when registering?
    - Mark as Best Seller
    - Can Have Additional Members? - When registering, does this membership allow additional members?
    - Select which benefits the membership has:
      - Up to 5 people
      - 2 people
      - 1 person
      - Pool use for season
      - Lakefront and Dock
      - Playground
      - Tennis/Pickleball Court
      - Dog Run
      - Pavilion
      - Membership Voting Rights
- New Member registration
  - Captcha to stop spammer
  - First name
  - Last name
  - Email
  - Street 1
  - Street 2
  - City
  - State
  - Zip
  - Country
  - Phone
  - Email
  - Membership selection
  - Password
  - Confirm password
  - Referral
  - Waiver Full Name
  - Waiver Signed Date
  - Payment Type
    - If by Card or Bank account we send them to Stripe to finish paying. We can send stripe the exact amount. We add 3% if paying by card. Stripe sends a webhook back to let us know if it was successful or not.
    - If paying by Zelle, Check, or Pay Later (special cases only) then we just send an email to the admin to let them know a user has requested that option. Also send an email to the new member's email on file.
- Sending Batch Emails
  - Send batch emails with our [no-reply@smoketree.us](mailto:no-reply@smoketree.us)
  - Admin to pick members based on filters like membership type, status, payment type, etc
  - Progress of batch being sent
  - Logging emails
  - Send multiple attachments
- Manage Codes
  - Members can view codes in the member portal. Admin should be able to CRUD the codes
- Guest Passes
  - Guest passes are \$5 each
  - All guest pass activity is logged
  - Admin can add to members account
  - Guests can buy as many as they like from the portal.
  - Guests will scan QR code to be taken to a page to use guest passes, buy first if they don't have any.
- Member portal
  - Member can view their membership details and status
  - Member can CRUD themselves
  - Member can change password
  - Member can add or use guest passes
  - Member can CRUD their family members
  - Member can CRUD their extra members (\$50 each if adding)
  - Members can go to their Stripe member portal and update payment information.

Every user should have a Smoketree account created and also a Stripe account created if registration was successful. We are currently using the Stripe PHP SDK. Webhooks are working between Stripe and the wordpress site. We are changing the member's status to "pending" or "active". Feel free to approach that totally differently. This is a new plugin.  

The admin dashboard (<https://smoketree.us/wp-admin/index.php>) surfaces live metrics and guest-pass analytics with shortcodes (total members, recent signups/activity, date-filterable guest pass log).

<email-template>
We currently have a folder of HTML Email templates:

- `payment-success.php` – thanks members immediately after Stripe confirms their payment.  
- `subscription-update.php` – alerts members whenever their subscription status changes (renewed, cancelled, etc.).  
- `password-reset.php` – delivers the secure reset link when a member requests a new password.  
- `payment-reminder.php` – nudges members whose membership invoice is still outstanding.  
- `thank-you-pay-later.php` – confirms “pay later” registrations and includes the amount still due.  
- `welcome.php` – full welcome package for standard memberships, including quick links and upcoming events.  
- `welcome-civic.php` – tailored welcome message for civic-only members with voting information.  
- `treasurer-pay-later.php` – notifies club officers that someone chose a manual payment option.  
- `notify-admin-of-member.php` – alerts admins about every new registration in real time.  
- `notify-admin-of-guest-pass.php` – lets staff know when guest passes are purchased.  
- `notify-admin-guest-pass-was-used.php` – logs each guest-pass redemption or admin adjustment for staff awareness.  
- `notify-admins-of-failed-registration.php` – flags unsuccessful registrations so admins can follow up.  
- `guest-pass-purchase.php` – sends members a receipt and usage instructions after buying guest passes.
</email-templates>

We will also build some wordpress template pages that talk to the PHP controllers to get data. Probably the registration page, member portal, and sign in/logout page.

I want the front end to do AJAX as much as possible so the members feel like it's a premium product their using. I would like to use tailwind type of styling.

I'm looking to collaborate with you to turn this into a detailed project request. Let's iterate together until we have a complete request that I find to be complete.


After each of our exchanges, please return the current state of the request in this format:

```request
# Project Name
## Project Description
[Description]

## Target Audience
[Target users]

## Desired Features
### [Feature Category]
- [ ] [Requirement]
    - [ ] [Sub-requirement]

## Design Requests
- [ ] [Design requirement]
    - [ ] [Design detail]

## Other Notes
- [Additional considerations]
```

Please:
1. Ask me questions about any areas that need more detail
2. Suggest features or considerations I might have missed
3. Help me organize requirements logically
4. Show me the current state of the spec after each exchange
5. Flag any potential technical challenges or important decisions

We'll continue iterating and refining the request until I indicate it's complete and ready.