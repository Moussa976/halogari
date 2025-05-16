<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => [
                    'autocomplete' => 'email',
                    'class' => 'form-control',
                    'placeholder' => 'exemple@halogari.yt',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre adresse e-mail.',
                    ]),
                    new Email([
                        'message' => "L'adresse e-mail n'est pas valide.",
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
