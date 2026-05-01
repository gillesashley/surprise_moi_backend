import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { register } from '@/routes/field-agents';
import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

type Bullet = { kind: 'bullet'; text: string };
type SubBullet = { kind: 'subBullet'; text: string };
type Para = { kind: 'para'; text: string };
type Item = Bullet | SubBullet | Para;

type Section = {
    heading: string;
    items: Item[];
};

const SECTIONS: Section[] = [
    {
        heading: '1.0 Introduction',
        items: [
            {
                kind: 'para',
                text: '1.1 These Terms & Conditions ("Terms") govern participation in the Surprise Moi Field Agent Program operated by Teczaleel Limited ("Company", "we", "us").',
            },
            {
                kind: 'para',
                text: '1.2 By registering as a Field Agent ("Agent"), you agree to be bound by these Terms.',
            },
            {
                kind: 'para',
                text: '1.3 These Terms establish the framework for vendor acquisition, vendor verification, agent conduct, compensation, and compliance enforcement.',
            },
        ],
    },
    {
        heading: '2.0 Role of Field Agents',
        items: [
            {
                kind: 'para',
                text: '2.1 A Field Agent is an authorized representative responsible for:',
            },
            { kind: 'bullet', text: 'Vendor acquisition' },
            { kind: 'bullet', text: 'Vendor onboarding' },
            { kind: 'bullet', text: 'In-person vendor verification' },
            { kind: 'para', text: '2.2 Agents serve as both:' },
            { kind: 'bullet', text: 'Growth drivers (vendor acquisition)' },
            {
                kind: 'bullet',
                text: 'Trust enforcers (verification and quality control)',
            },
            {
                kind: 'para',
                text: '2.3 Agents operate strictly as independent contractors.',
            },
            { kind: 'para', text: '2.4 Nothing in this Agreement creates:' },
            { kind: 'bullet', text: 'Employment' },
            { kind: 'bullet', text: 'Partnership' },
            { kind: 'bullet', text: 'Joint venture' },
            { kind: 'bullet', text: 'Agency relationship' },
        ],
    },
    {
        heading: '3.0 Eligibility & Registration',
        items: [
            {
                kind: 'para',
                text: '3.1 Agents must provide accurate, complete, and truthful information during registration.',
            },
            {
                kind: 'para',
                text: '3.2 Required information may include:',
            },
            { kind: 'bullet', text: 'Full name' },
            { kind: 'bullet', text: 'Phone number' },
            { kind: 'bullet', text: 'Location' },
            { kind: 'bullet', text: 'Government-issued ID (Ghana Card)' },
            { kind: 'bullet', text: 'Selfie verification' },
            { kind: 'bullet', text: 'Payment details' },
            {
                kind: 'para',
                text: '3.3 The Company reserves the right to:',
            },
            { kind: 'bullet', text: 'Approve or reject any application' },
            { kind: 'bullet', text: 'Request additional verification' },
            { kind: 'bullet', text: 'Revoke access where necessary' },
        ],
    },
    {
        heading: '4.0 Mandatory Training Requirement',
        items: [
            {
                kind: 'para',
                text: '4.1 Participation in the Field Agent Program is subject to mandatory training.',
            },
            { kind: 'para', text: '4.2 All Agents must:' },
            {
                kind: 'bullet',
                text: 'Attend official training sessions organized by the Company',
            },
            { kind: 'bullet', text: 'Demonstrate understanding of:' },
            { kind: 'subBullet', text: 'Vendor acquisition' },
            { kind: 'subBullet', text: 'Vendor verification' },
            { kind: 'subBullet', text: 'Platform positioning' },
            { kind: 'subBullet', text: 'Operational procedures' },
            {
                kind: 'para',
                text: '4.3 No Agent shall be approved, activated, or permitted to operate unless:',
            },
            { kind: 'bullet', text: 'Training has been completed; and' },
            {
                kind: 'bullet',
                text: 'The Agent has been cleared by the Company',
            },
        ],
    },
    {
        heading: '5.0 Field Agent Obligations',
        items: [
            { kind: 'para', text: 'The Agent agrees to:' },
            {
                kind: 'para',
                text: '5.1 Conduct vendor acquisition professionally and ethically',
            },
            {
                kind: 'para',
                text: '5.2 Ensure all vendor registrations are completed in their presence',
            },
            {
                kind: 'para',
                text: '5.3 Conduct real-time, on-site verification',
            },
            {
                kind: 'para',
                text: '5.4 Submit accurate and complete verification data, including:',
            },
            { kind: 'bullet', text: 'Photos' },
            { kind: 'bullet', text: 'Videos' },
            { kind: 'bullet', text: 'Supporting evidence' },
            {
                kind: 'para',
                text: '5.5 Represent the Company professionally at all times',
            },
            {
                kind: 'para',
                text: '5.6 Avoid false, misleading, or exaggerated claims',
            },
        ],
    },
    {
        heading: '6.0 Vendor Verification Requirements',
        items: [
            {
                kind: 'para',
                text: '6.1 Verification must be conducted immediately after vendor registration.',
            },
            { kind: 'para', text: '6.2 Verification includes:' },
            { kind: 'bullet', text: 'Identity confirmation' },
            { kind: 'bullet', text: 'Business authenticity' },
            { kind: 'bullet', text: 'Product/service validation' },
            { kind: 'bullet', text: 'Contact verification' },
            {
                kind: 'para',
                text: '6.3 Required evidence must be captured and uploaded at the time of verification.',
            },
            {
                kind: 'para',
                text: '6.4 If a vendor is registered but not verified immediately:',
            },
            { kind: 'bullet', text: 'The onboarding process is incomplete' },
            { kind: 'bullet', text: 'The Agent will not earn commission' },
            { kind: 'bullet', text: 'The vendor may be rejected' },
        ],
    },
    {
        heading: '7.0 Prohibited Actions',
        items: [
            { kind: 'para', text: 'Agents shall NOT:' },
            { kind: 'bullet', text: 'Falsify verification data' },
            { kind: 'bullet', text: 'Conduct remote or delayed verification' },
            { kind: 'bullet', text: 'Approve vendors independently' },
            {
                kind: 'bullet',
                text: 'Submit incomplete or misleading information',
            },
            {
                kind: 'para',
                text: 'Violation may result in immediate suspension or termination.',
            },
        ],
    },
    {
        heading: '8.0 Compensation & Payment',
        items: [
            {
                kind: 'para',
                text: '8.1 Agents earn commission per successfully onboarded vendor.',
            },
            {
                kind: 'para',
                text: '8.2 A vendor is considered successfully onboarded only when:',
            },
            { kind: 'bullet', text: 'Registration is completed' },
            { kind: 'bullet', text: 'Verification is submitted' },
            { kind: 'bullet', text: 'Vendor is approved' },
            { kind: 'bullet', text: 'Vendor onboarding fee is paid' },
            { kind: 'para', text: '8.3 Payments may be subject to:' },
            { kind: 'bullet', text: 'Review' },
            { kind: 'bullet', text: 'Fraud checks' },
            { kind: 'bullet', text: 'Verification audits' },
        ],
    },
    {
        heading: '9.0 Performance Expectations',
        items: [
            { kind: 'para', text: 'Agents are expected to:' },
            { kind: 'bullet', text: 'Meet onboarding targets' },
            { kind: 'bullet', text: 'Maintain high verification accuracy' },
            { kind: 'bullet', text: 'Onboard legitimate vendors' },
            { kind: 'para', text: 'Performance may be monitored through:' },
            { kind: 'bullet', text: 'Approval rate' },
            { kind: 'bullet', text: 'Activity consistency' },
            { kind: 'bullet', text: 'Quality of submissions' },
        ],
    },
    {
        heading: '10.0 Code of Conduct',
        items: [
            { kind: 'para', text: 'Agents must:' },
            { kind: 'bullet', text: 'Act with integrity' },
            { kind: 'bullet', text: 'Respect vendors' },
            { kind: 'bullet', text: 'Represent the Company professionally' },
            { kind: 'para', text: 'Agents must NOT:' },
            { kind: 'bullet', text: 'Harass vendors' },
            { kind: 'bullet', text: 'Misrepresent the platform' },
            {
                kind: 'bullet',
                text: 'Engage in unauthorized or off-platform transactions',
            },
        ],
    },
    {
        heading: '11.0 Confidentiality',
        items: [
            {
                kind: 'para',
                text: '11.1 Agents agree to maintain strict confidentiality of:',
            },
            { kind: 'bullet', text: 'Vendor data' },
            { kind: 'bullet', text: 'Platform operations' },
            { kind: 'bullet', text: 'Internal processes' },
            {
                kind: 'para',
                text: '11.2 Unauthorized disclosure is prohibited and may result in legal action.',
            },
            {
                kind: 'para',
                text: 'The Field Agent must not develop similar application, solicit the application idea to another, and provide ideation to another company on how the product works.',
            },
        ],
    },
    {
        heading: '12.0 Non-Competition',
        items: [
            {
                kind: 'para',
                text: '12.1 During participation and for a period of twenty-four (24) months following termination, the Agent shall not, directly or indirectly:',
            },
            {
                kind: 'bullet',
                text: 'Engage in, operate, or be employed by any competing business;',
            },
            {
                kind: 'bullet',
                text: 'Assist, advise, or support competing platforms;',
            },
            {
                kind: 'bullet',
                text: 'Use vendor relationships, knowledge, or operational processes obtained through the program for competing purposes.',
            },
            { kind: 'para', text: '12.2 This restriction applies within:' },
            { kind: 'bullet', text: 'Ghana' },
            {
                kind: 'bullet',
                text: 'Any market where the Company operates at termination',
            },
            {
                kind: 'para',
                text: '12.3 A competing business includes any platform offering:',
            },
            { kind: 'bullet', text: 'Vendor onboarding' },
            { kind: 'bullet', text: 'Marketplace services' },
            { kind: 'bullet', text: 'Digital commerce solutions' },
            {
                kind: 'para',
                text: '12.4 If an Agent uses vendor contacts obtained during the program to onboard vendors for a competing platform:',
            },
            { kind: 'bullet', text: 'This constitutes a breach of these Terms' },
            { kind: 'bullet', text: 'Legal action may be pursued' },
        ],
    },
    {
        heading: '13.0 Data Protection',
        items: [
            { kind: 'para', text: '13.1 Agents shall not:' },
            { kind: 'bullet', text: 'Store personal data outside the platform' },
            { kind: 'bullet', text: 'Share or disclose vendor information' },
            {
                kind: 'bullet',
                text: 'Use data for personal or external business purposes',
            },
            {
                kind: 'para',
                text: '13.2 Data must be used strictly for onboarding and verification purposes.',
            },
        ],
    },
    {
        heading: '14.0 Suspension & Termination',
        items: [
            {
                kind: 'para',
                text: '14.1 The Company may suspend or terminate an Agent for:',
            },
            { kind: 'bullet', text: 'Fraud' },
            { kind: 'bullet', text: 'Policy violations' },
            { kind: 'bullet', text: 'Data misuse' },
            { kind: 'bullet', text: 'Misconduct' },
            { kind: 'bullet', text: 'Poor performance' },
            {
                kind: 'para',
                text: '14.2 Termination may occur without prior notice in serious cases.',
            },
        ],
    },
    {
        heading: '15.0 Limitation of Liability',
        items: [
            {
                kind: 'para',
                text: '15.1 The Agent participates in the program at their own risk.',
            },
            { kind: 'para', text: '15.2 The Company shall not be liable for:' },
            { kind: 'bullet', text: 'Loss of income' },
            { kind: 'bullet', text: 'Missed opportunities' },
            { kind: 'bullet', text: 'Indirect or consequential damages' },
        ],
    },
    {
        heading: '16.0 Amendments',
        items: [
            {
                kind: 'para',
                text: '16.1 The Company reserves the right to update these Terms at any time.',
            },
            {
                kind: 'para',
                text: '16.2 Continued participation constitutes acceptance of revised Terms.',
            },
        ],
    },
    {
        heading: '17.0 Governing Law',
        items: [
            {
                kind: 'para',
                text: 'These Terms shall be governed by the laws of Ghana.',
            },
        ],
    },
    {
        heading: '18.0 Acceptance & Acknowledgment',
        items: [
            {
                kind: 'para',
                text: 'By registering and participating in the Field Agent Program, the Agent confirms that they:',
            },
            { kind: 'bullet', text: 'Have read and understood these Terms' },
            { kind: 'bullet', text: 'Agree to comply with all provisions' },
            {
                kind: 'bullet',
                text: 'Understand their responsibilities and obligations',
            },
        ],
    },
];

function ItemRow({ item }: { item: Item }) {
    if (item.kind === 'para') {
        return (
            <Typography variant="body2" sx={{ lineHeight: 1.7 }}>
                {item.text}
            </Typography>
        );
    }
    if (item.kind === 'bullet') {
        return (
            <Box sx={{ display: 'flex', gap: 1, pl: 2 }}>
                <Typography variant="body2" sx={{ lineHeight: 1.7 }}>
                    •
                </Typography>
                <Typography variant="body2" sx={{ lineHeight: 1.7, flex: 1 }}>
                    {item.text}
                </Typography>
            </Box>
        );
    }
    return (
        <Box sx={{ display: 'flex', gap: 1, pl: 4 }}>
            <Typography variant="body2" sx={{ lineHeight: 1.7 }}>
                ◦
            </Typography>
            <Typography variant="body2" sx={{ lineHeight: 1.7, flex: 1 }}>
                {item.text}
            </Typography>
        </Box>
    );
}

export default function FieldAgentTerms() {
    return (
        <>
            <Head title="Field Agent Terms & Conditions" />
            <Box
                sx={{
                    display: 'flex',
                    minHeight: '100svh',
                    flexDirection: 'column',
                    alignItems: 'center',
                    bgcolor: 'background.default',
                    p: { xs: 2, md: 5 },
                }}
            >
                <Box sx={{ width: '100%', maxWidth: '52rem' }}>
                    <Box
                        sx={{
                            bgcolor: 'background.paper',
                            borderRadius: 3,
                            boxShadow: (theme) => theme.shadows[2],
                            p: { xs: 3, md: 5 },
                        }}
                    >
                        <Stack
                            spacing={1.5}
                            sx={{ alignItems: 'center', textAlign: 'center', mb: 4 }}
                        >
                            <Link
                                href={home()}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: 'inherit',
                                    textDecoration: 'none',
                                }}
                            >
                                <AppLogoIcon style={{ width: 40, height: 40 }} />
                            </Link>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                Surprise Moi Field Agent Terms & Conditions
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                Please read these Terms in full before registering as a Field Agent.
                            </Typography>
                        </Stack>

                        <Stack spacing={4}>
                            {SECTIONS.map((section) => (
                                <Stack key={section.heading} spacing={1}>
                                    <Typography
                                        variant="subtitle1"
                                        sx={{ fontWeight: 600 }}
                                    >
                                        {section.heading}
                                    </Typography>
                                    <Stack spacing={0.75}>
                                        {section.items.map((item, idx) => (
                                            <ItemRow
                                                key={`${section.heading}-${idx}`}
                                                item={item}
                                            />
                                        ))}
                                    </Stack>
                                </Stack>
                            ))}
                        </Stack>

                        <Box
                            sx={{
                                mt: 5,
                                pt: 3,
                                borderTop: 1,
                                borderColor: 'divider',
                                display: 'flex',
                                justifyContent: 'space-between',
                                gap: 2,
                                flexWrap: 'wrap',
                            }}
                        >
                            <Button asChild variant="outline">
                                <Link href={home()}>Back to home</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>
                                    Continue to registration
                                </Link>
                            </Button>
                        </Box>
                    </Box>
                </Box>
            </Box>
        </>
    );
}
