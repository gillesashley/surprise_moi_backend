### **Field Agent Program – Functional Specification (Refined)**

#### **1. Objective**

Redefine and implement the correct workflow for the Field Agent Program. Field agents are responsible exclusively for **vendor acquisition (onboarding new vendors)**, not validation of existing vendors. All legacy functionality that exposes existing vendor data to field agents must be removed.

I meant when you visit http://localhost:8082/field-agent/visits, currently the vendors see the list of our existing vendors, When they click on a vendor, the logged in field agent sees all the vendor details. This is what we are taking out. So with the new design, the field agent only sees the vendors' names maybe, when they click, they  then see the questionnaires. So every new vendor will have that questionaire associated to them.
---

#### **2. Field Agent Responsibilities**

Field agents are tasked with:

* Identifying and engaging **new vendors** in markets, shops, and commercial areas.
* Assisting vendors with:

  * Downloading the mobile application
  * Creating a vendor account
* Providing their **referral code** during vendor registration

#### **Referral System Logic**

* Each field agent has a unique referral code.
* During vendor signup:

  * Vendor enters the referral code.
  * System associates the vendor with the corresponding field agent.
* Upon **successful vendor approval**:

  * The field agent earns a **commission/reward/points**.

---

#### **3. Vendor Onboarding Workflow**

1. Field agent approaches a new vendor.
2. Vendor agrees to join the platform.
3. Field agent:

   * Guides vendor through app installation
   * Assists with vendor account creation
   * Ensures referral code is entered
4. Vendor submits application.

---

#### **4. Post-Application Workflow (Field Agent Dashboard)**

Once a vendor application is submitted:

* The associated field agent should:

  * Immediately see the vendor listed on their **Field Agent Dashboard**
  * Select the vendor entry to open a **Questionnaire Form**

* Field agent must:

  * Interview the vendor
  * Fill out and submit the questionnaire

---

#### **5. Questionnaire Requirements**

The questionnaire should capture the following:

* **Ghana Card Number**
* **TIN (Tax Identification Number)** *(if registered business)*
* **Does the vendor have a shop?** *(Yes/No)*

  **If YES:**

  * Shop location
  * Upload photographic evidence of the shop

  **If NO:**

  * Primary business address/location

*(Extendable for additional verification fields as needed.)*

---

#### **6. Admin Review Workflow**

Admins access submissions via:
`/vendor-visit`

For each vendor:

1. Display:

   * Vendor details (from application)
   * Questionnaire responses (from field agent)
   * Associated field agent (derived from referral code mapping)

2. Perform:

   * **Data validation and comparison**

     * Vendor-submitted data vs. field agent responses

3. Decision:

   * If data is consistent → **Approve vendor**
   * If inconsistent → **Reject or flag for review**

---

#### **7. Reward System**

* Upon vendor approval:

  * The referring field agent is automatically credited with:

    * Points / commission / rewards
* Field agents can track:

  * Total referrals
  * Approved vendors
  * Earned rewards
    via their dashboard

---

#### **8. Access Control & Restrictions**

* Field agents **must NOT**:

  * View existing vendors
  * Access or validate pre-existing vendor data

* Action required:

  * Remove any system feature exposing **existing vendor records** to field agents

---

#### **9. Operational Constraints**

* No limit on the number of vendors a field agent can onboard per day
* System must support:

  * High-volume onboarding
  * Scalable referral tracking

---

#### **10. Implementation Considerations**

* Strong linkage between:

  * `vendor.referral_code` → `field_agent.id`
* Ensure:

  * Audit trail for questionnaire submissions
  * Timestamped actions (submission, approval, reward allocation)
* Enforce:

  * Validation rules on questionnaire inputs
  * Media upload handling (for shop evidence)

