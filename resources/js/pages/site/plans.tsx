import { Head } from '@inertiajs/react';
import { PlanCard } from '@/components/site/plan-card';
import { Container, Section } from '@/components/site/section';
import type { Plan, SiteShared } from '@/types/catalog';

type PlansProps = {
    site: SiteShared;
    plans: Plan[];
};

const questions = [
    {
        question: 'Can I use a plan at any branch?',
        answer: 'Plans run at the branch you bought them at. If we open another chair near you, we will move it across rather than make you buy twice.',
    },
    {
        question: 'Do points still earn on a plan?',
        answer: 'Yes. Plan visits still earn points, and products bought at the counter earn as normal.',
    },
    {
        question: 'What happens if I miss a month?',
        answer: 'Session packs run for their validity window and then stop. Nothing renews on you silently. Unlimited plans you choose to renew each month.',
    },
    {
        question: 'Can I share a plan?',
        answer: 'A plan sits on one client record, because it carries your cut history and your barber preference. For family, ask us about a second account.',
    },
];

export default function Plans({ plans }: PlansProps) {
    return (
        <>
            <Head title="Monthly plans">
                <meta
                    name="description"
                    content="Monthly cut plans and facial packages at Magnetic Barbershop. Pay once, cut all month."
                />
            </Head>

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-16 sm:py-20">
                    <p className="site-eyebrow mb-4">Renewable plans</p>
                    <h1 className="site-display max-w-2xl text-4xl sm:text-5xl lg:text-6xl">
                        Pay once, cut all month.
                    </h1>
                    <p className="text-smoke mt-5 max-w-xl leading-relaxed">
                        Predictable income for us, real savings for you. Every
                        plan still writes to the same client record, so your
                        style history follows you.
                    </p>
                </Container>
            </section>

            <Section>
                {plans.length === 0 ? (
                    <p className="text-smoke">No plans are published yet.</p>
                ) : (
                    <div className="grid items-start gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard key={plan.slug} plan={plan} />
                        ))}
                    </div>
                )}
            </Section>

            <Section
                className="bg-panel/30"
                eyebrow="Before you buy"
                title="The fine print, in plain words."
            >
                <dl className="grid gap-5 md:grid-cols-2">
                    {questions.map((item) => (
                        <div key={item.question} className="site-panel p-6">
                            <dt className="text-bone font-semibold">
                                {item.question}
                            </dt>
                            <dd className="text-smoke mt-2 text-sm leading-relaxed">
                                {item.answer}
                            </dd>
                        </div>
                    ))}
                </dl>
            </Section>
        </>
    );
}
