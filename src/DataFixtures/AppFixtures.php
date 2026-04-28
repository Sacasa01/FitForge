<?php

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\Food;
use App\Entity\Diet;
use App\Entity\Meal;
use App\Entity\MealFood;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Enum\GoalType;
use App\Enum\Difficulty;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Clear existing data in correct order due to foreign keys
        $manager->getConnection()->executeStatement('DELETE FROM routine_exercises');
        $manager->getConnection()->executeStatement('DELETE FROM meal_foods');
        $manager->getConnection()->executeStatement('DELETE FROM meals');
        $manager->getConnection()->executeStatement('DELETE FROM diets');
        $manager->getConnection()->executeStatement('DELETE FROM routines');
        $manager->getConnection()->executeStatement('DELETE FROM foods');
        $manager->getConnection()->executeStatement('DELETE FROM exercises');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE exercises_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE foods_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE diets_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE meals_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE meal_foods_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE routines_id_seq RESTART WITH 1');
        $manager->getConnection()->executeStatement('ALTER SEQUENCE routine_exercises_id_seq RESTART WITH 1');

        // ==================== EXERCISES ====================
        $exercises = $this->createExercises();
        foreach ($exercises as $exercise) {
            $manager->persist($exercise);
        }

        // ==================== FOODS ====================
        $foods = $this->createFoods();
        foreach ($foods as $food) {
            $manager->persist($food);
        }

        $manager->flush();

        // ==================== ROUTINES ====================
        $routines = $this->createRoutines($exercises);
        foreach ($routines as $routine) {
            $manager->persist($routine);
        }

        // ==================== DIETS ====================
        $diets = $this->createDiets($foods);
        foreach ($diets as $diet) {
            $manager->persist($diet);
        }

        $manager->flush();
        echo "Seeded " . count($exercises) . " exercises\n";
        echo "Seeded " . count($foods) . " foods\n";
        echo "Seeded " . count($routines) . " routines\n";
        echo "Seeded " . count($diets) . " diets\n";
    }

    private function createExercises(): array
    {
        $exercises = [];

        // Chest exercises
        $exercises[] = (new Exercise())
            ->setName('Bench Press')
            ->setDescription('Lie flat on bench, grip bar slightly wider than shoulders, lower to chest and push up.')
            ->setMuscleGroup('chest')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('8.50');

        $exercises[] = (new Exercise())
            ->setName('Incline Dumbbell Press')
            ->setDescription('On incline bench (30-45°), press dumbbells from shoulder level to above chest.')
            ->setMuscleGroup('chest')
            ->setEquipment('dumbbells')
            ->setCaloriesPerMin('7.00');

        $exercises[] = (new Exercise())
            ->setName('Push-ups')
            ->setDescription('Standard push-up position, lower chest to floor, push back up. Keep core engaged.')
            ->setMuscleGroup('chest')
            ->setEquipment('bodyweight')
            ->setCaloriesPerMin('6.50');

        $exercises[] = (new Exercise())
            ->setName('Cable Flyes')
            ->setDescription('Stand between cable pulleys, bring handles together in front of chest with slight bend in elbows.')
            ->setMuscleGroup('chest')
            ->setEquipment('cable machine')
            ->setCaloriesPerMin('5.00');

        // Back exercises
        $exercises[] = (new Exercise())
            ->setName('Deadlift')
            ->setDescription('Stand with feet hip-width, grip bar, drive through heels to stand erect, keeping back straight.')
            ->setMuscleGroup('back')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('10.00');

        $exercises[] = (new Exercise())
            ->setName('Pull-ups')
            ->setDescription('Hang from bar, pull yourself up until chin passes bar. Control the descent.')
            ->setMuscleGroup('back')
            ->setEquipment('pull-up bar')
            ->setCaloriesPerMin('8.00');

        $exercises[] = (new Exercise())
            ->setName('Barbell Rows')
            ->setDescription('Bend over with flat back, pull barbell to lower chest, squeezing shoulder blades.')
            ->setMuscleGroup('back')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('8.50');

        $exercises[] = (new Exercise())
            ->setName('Lat Pulldown')
            ->setDescription('Sit at lat pulldown machine, pull bar to upper chest, leaning back slightly.')
            ->setMuscleGroup('back')
            ->setEquipment('cable machine')
            ->setCaloriesPerMin('6.00');

        // Legs exercises
        $exercises[] = (new Exercise())
            ->setName('Squats')
            ->setDescription('Stand with bar on shoulders, feet shoulder-width, squat down until thighs parallel to floor.')
            ->setMuscleGroup('legs')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('10.00');

        $exercises[] = (new Exercise())
            ->setName('Leg Press')
            ->setDescription('Sit in leg press machine, push platform away with legs, control back down.')
            ->setMuscleGroup('legs')
            ->setEquipment('leg press machine')
            ->setCaloriesPerMin('7.00');

        $exercises[] = (new Exercise())
            ->setName('Lunges')
            ->setDescription('Step forward into lunge, lower back knee toward ground, push back to start.')
            ->setMuscleGroup('legs')
            ->setEquipment('dumbbells')
            ->setCaloriesPerMin('7.50');

        $exercises[] = (new Exercise())
            ->setName('Romanian Deadlift')
            ->setDescription('Hold barbell in front, hinge at hips keeping legs straight, lower to mid-shin, return.')
            ->setMuscleGroup('legs')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('8.00');

        // Shoulder exercises
        $exercises[] = (new Exercise())
            ->setName('Overhead Press')
            ->setDescription('Stand holding barbell at shoulders, press overhead until arms are fully extended.')
            ->setMuscleGroup('shoulders')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('7.00');

        $exercises[] = (new Exercise())
            ->setName('Lateral Raises')
            ->setDescription('Stand holding dumbbells, raise arms to sides until parallel to floor, slight elbow bend.')
            ->setMuscleGroup('shoulders')
            ->setEquipment('dumbbells')
            ->setCaloriesPerMin('4.50');

        $exercises[] = (new Exercise())
            ->setName('Face Pulls')
            ->setDescription('Attach rope to cable at face height, pull toward face, separating hands at end.')
            ->setMuscleGroup('shoulders')
            ->setEquipment('cable machine')
            ->setCaloriesPerMin('5.00');

        // Arms exercises
        $exercises[] = (new Exercise())
            ->setName('Barbell Curls')
            ->setDescription('Stand holding barbell, curl weight to chest, keeping elbows stationary.')
            ->setMuscleGroup('biceps')
            ->setEquipment('barbell')
            ->setCaloriesPerMin('4.50');

        $exercises[] = (new Exercise())
            ->setName('Hammer Curls')
            ->setDescription('Hold dumbbells with neutral grip, curl weights to shoulders, palms facing each other.')
            ->setMuscleGroup('biceps')
            ->setEquipment('dumbbells')
            ->setCaloriesPerMin('4.00');

        $exercises[] = (new Exercise())
            ->setName('Tricep Pushdown')
            ->setDescription('Stand at cable machine, push bar down, extending arms fully, keeping elbows tucked.')
            ->setMuscleGroup('triceps')
            ->setEquipment('cable machine')
            ->setCaloriesPerMin('5.00');

        $exercises[] = (new Exercise())
            ->setName('Skull Crushers')
            ->setDescription('Lie on bench holding EZ bar, lower bar to forehead by bending elbows, extend back up.')
            ->setMuscleGroup('triceps')
            ->setEquipment('ez bar')
            ->setCaloriesPerMin('5.50');

        // Core exercises
        $exercises[] = (new Exercise())
            ->setName('Plank')
            ->setDescription('Hold push-up position on forearms, keep body straight, engage core.')
            ->setMuscleGroup('core')
            ->setEquipment('bodyweight')
            ->setCaloriesPerMin('4.00');

        $exercises[] = (new Exercise())
            ->setName('Hanging Leg Raises')
            ->setDescription('Hang from bar, raise legs to 90°, lower slowly. Control the movement.')
            ->setMuscleGroup('core')
            ->setEquipment('pull-up bar')
            ->setCaloriesPerMin('6.00');

        $exercises[] = (new Exercise())
            ->setName('Cable Crunches')
            ->setDescription('Kneel facing cable machine, crunch forward bringing elbows to knees.')
            ->setMuscleGroup('core')
            ->setEquipment('cable machine')
            ->setCaloriesPerMin('5.00');

        // Cardio exercises
        $exercises[] = (new Exercise())
            ->setName('Treadmill Running')
            ->setDescription('Run at steady pace or intervals. Adjust incline for variety.')
            ->setMuscleGroup('cardio')
            ->setEquipment('treadmill')
            ->setCaloriesPerMin('11.00');

        $exercises[] = (new Exercise())
            ->setName('Cycling')
            ->setDescription('Stationary bike session. Adjust resistance for desired intensity.')
            ->setMuscleGroup('cardio')
            ->setEquipment('stationary bike')
            ->setCaloriesPerMin('9.00');

        $exercises[] = (new Exercise())
            ->setName('Rowing Machine')
            ->setDescription('Full body cardio. Drive with legs, pull with arms, return with control.')
            ->setMuscleGroup('cardio')
            ->setEquipment('rowing machine')
            ->setCaloriesPerMin('10.00');

        return $exercises;
    }

    private function createFoods(): array
    {
        $foods = [];

        // Proteins
        $foods[] = (new Food())
            ->setName('Chicken Breast')
            ->setKcalPer100g('165')
            ->setProteinG('31')
            ->setCarbsG('0')
            ->setFatG('3.6')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Salmon Fillet')
            ->setKcalPer100g('208')
            ->setProteinG('20')
            ->setCarbsG('0')
            ->setFatG('13')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Lean Ground Beef')
            ->setKcalPer100g('250')
            ->setProteinG('26')
            ->setCarbsG('0')
            ->setFatG('15')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Eggs')
            ->setKcalPer100g('155')
            ->setProteinG('13')
            ->setCarbsG('1.1')
            ->setFatG('11')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Greek Yogurt')
            ->setKcalPer100g('59')
            ->setProteinG('10')
            ->setCarbsG('3.6')
            ->setFatG('0.4')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Cottage Cheese')
            ->setKcalPer100g('98')
            ->setProteinG('11')
            ->setCarbsG('3.4')
            ->setFatG('4.3')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Tuna')
            ->setKcalPer100g('116')
            ->setProteinG('26')
            ->setCarbsG('0')
            ->setFatG('1')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Turkey Breast')
            ->setKcalPer100g('135')
            ->setProteinG('30')
            ->setCarbsG('0')
            ->setFatG('1')
            ->setFiberG('0');

        // Carbohydrates
        $foods[] = (new Food())
            ->setName('Brown Rice')
            ->setKcalPer100g('111')
            ->setProteinG('2.6')
            ->setCarbsG('23')
            ->setFatG('0.9')
            ->setFiberG('1.8');

        $foods[] = (new Food())
            ->setName('Sweet Potato')
            ->setKcalPer100g('86')
            ->setProteinG('1.6')
            ->setCarbsG('20')
            ->setFatG('0.1')
            ->setFiberG('3');

        $foods[] = (new Food())
            ->setName('Oats')
            ->setKcalPer100g('389')
            ->setProteinG('16.9')
            ->setCarbsG('66')
            ->setFatG('6.9')
            ->setFiberG('10.6');

        $foods[] = (new Food())
            ->setName('Quinoa')
            ->setKcalPer100g('120')
            ->setProteinG('4.4')
            ->setCarbsG('21')
            ->setFatG('1.9')
            ->setFiberG('2.8');

        $foods[] = (new Food())
            ->setName('Whole Wheat Pasta')
            ->setKcalPer100g('124')
            ->setProteinG('5.3')
            ->setCarbsG('26')
            ->setFatG('0.5')
            ->setFiberG('4.5');

        $foods[] = (new Food())
            ->setName('Banana')
            ->setKcalPer100g('89')
            ->setProteinG('1.1')
            ->setCarbsG('23')
            ->setFatG('0.3')
            ->setFiberG('2.6');

        $foods[] = (new Food())
            ->setName('Whole Wheat Bread')
            ->setKcalPer100g('247')
            ->setProteinG('13')
            ->setCarbsG('41')
            ->setFatG('3.4')
            ->setFiberG('7');

        // Vegetables
        $foods[] = (new Food())
            ->setName('Broccoli')
            ->setKcalPer100g('34')
            ->setProteinG('2.8')
            ->setCarbsG('7')
            ->setFatG('0.4')
            ->setFiberG('2.6');

        $foods[] = (new Food())
            ->setName('Spinach')
            ->setKcalPer100g('23')
            ->setProteinG('2.9')
            ->setCarbsG('3.6')
            ->setFatG('0.4')
            ->setFiberG('2.2');

        $foods[] = (new Food())
            ->setName('Asparagus')
            ->setKcalPer100g('20')
            ->setProteinG('2.2')
            ->setCarbsG('3.9')
            ->setFatG('0.1')
            ->setFiberG('2.1');

        $foods[] = (new Food())
            ->setName('Bell Peppers')
            ->setKcalPer100g('31')
            ->setProteinG('1')
            ->setCarbsG('6')
            ->setFatG('0.3')
            ->setFiberG('2.1');

        $foods[] = (new Food())
            ->setName('Avocado')
            ->setKcalPer100g('160')
            ->setProteinG('2')
            ->setCarbsG('9')
            ->setFatG('15')
            ->setFiberG('7');

        // Healthy Fats
        $foods[] = (new Food())
            ->setName('Almonds')
            ->setKcalPer100g('579')
            ->setProteinG('21')
            ->setCarbsG('22')
            ->setFatG('50')
            ->setFiberG('12');

        $foods[] = (new Food())
            ->setName('Olive Oil')
            ->setKcalPer100g('884')
            ->setProteinG('0')
            ->setCarbsG('0')
            ->setFatG('100')
            ->setFiberG('0');

        $foods[] = (new Food())
            ->setName('Peanut Butter')
            ->setKcalPer100g('588')
            ->setProteinG('25')
            ->setCarbsG('20')
            ->setFatG('50')
            ->setFiberG('6');

        // Fruits
        $foods[] = (new Food())
            ->setName('Apple')
            ->setKcalPer100g('52')
            ->setProteinG('0.3')
            ->setCarbsG('14')
            ->setFatG('0.2')
            ->setFiberG('2.4');

        $foods[] = (new Food())
            ->setName('Blueberries')
            ->setKcalPer100g('57')
            ->setProteinG('0.7')
            ->setCarbsG('14')
            ->setFatG('0.3')
            ->setFiberG('2.4');

        $foods[] = (new Food())
            ->setName('Orange')
            ->setKcalPer100g('47')
            ->setProteinG('0.9')
            ->setCarbsG('12')
            ->setFatG('0.1')
            ->setFiberG('2.4');

        return $foods;
    }

    private function createRoutines(array $exercises): array
    {
        $routines = [];
        $exMap = [];
        foreach ($exercises as $ex) {
            $exMap[$ex->getName()] = $ex;
        }

        // 1. Full Body Beginner Routine
        $routine1 = new Routine();
        $routine1->setName('Full Body Beginner')
            ->setDescription('A balanced full-body workout for beginners focusing on compound movements.')
            ->setDifficulty(Difficulty::Beginner)
            ->setGoalType(GoalType::Maintenance);

        $order = 0;
        $routine1->addRoutineExercise($this->createRoutineExercise($exMap['Squats'], 3, 12, $order++));
        $routine1->addRoutineExercise($this->createRoutineExercise($exMap['Bench Press'], 3, 10, $order++));
        $routine1->addRoutineExercise($this->createRoutineExercise($exMap['Barbell Rows'], 3, 10, $order++));
        $routine1->addRoutineExercise($this->createRoutineExercise($exMap['Overhead Press'], 3, 8, $order++));
        $routine1->addRoutineExercise($this->createRoutineExercise($exMap['Plank'], 3, 30, $order++));
        $routines[] = $routine1;

        // 2. Push/Pull/Legs - Intermediate
        $routine2 = new Routine();
        $routine2->setName('Push/Pull/Legs Split')
            ->setDescription('Classic PPL split for intermediate lifters. Rotate through three workout days.')
            ->setDifficulty(Difficulty::Intermediate)
            ->setGoalType(GoalType::MuscleGain);

        $order = 0;
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Bench Press'], 4, 8, $order++));
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Incline Dumbbell Press'], 3, 10, $order++));
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Overhead Press'], 4, 8, $order++));
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Lateral Raises'], 3, 12, $order++));
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Tricep Pushdown'], 3, 12, $order++));
        $routine2->addRoutineExercise($this->createRoutineExercise($exMap['Push-ups'], 3, 15, $order++));
        $routines[] = $routine2;

        // 3. Pull Day
        $routine3 = new Routine();
        $routine3->setName('Pull Day')
            ->setDescription('Focus on back and biceps. Pair with Push Day and Leg Day for complete split.')
            ->setDifficulty(Difficulty::Intermediate)
            ->setGoalType(GoalType::MuscleGain);

        $order = 0;
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Deadlift'], 4, 6, $order++));
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Pull-ups'], 4, 8, $order++));
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Barbell Rows'], 4, 10, $order++));
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Lat Pulldown'], 3, 12, $order++));
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Face Pulls'], 3, 15, $order++));
        $routine3->addRoutineExercise($this->createRoutineExercise($exMap['Barbell Curls'], 3, 12, $order++));
        $routines[] = $routine3;

        // 4. Leg Day
        $routine4 = new Routine();
        $routine4->setName('Leg Day')
            ->setDescription('Intense leg training for intermediate to advanced lifters.')
            ->setDifficulty(Difficulty::Intermediate)
            ->setGoalType(GoalType::MuscleGain);

        $order = 0;
        $routine4->addRoutineExercise($this->createRoutineExercise($exMap['Squats'], 4, 8, $order++));
        $routine4->addRoutineExercise($ex = new RoutineExercise());
        $ex->setExercise($exMap['Romanian Deadlift'])->setSets(4)->setReps(10)->setOrderIndex($order++);
        $routine4->addRoutineExercise($ex);
        $routine4->addRoutineExercise($this->createRoutineExercise($exMap['Leg Press'], 3, 12, $order++));
        $routine4->addRoutineExercise($this->createRoutineExercise($exMap['Lunges'], 3, 10, $order++));
        $routine4->addRoutineExercise($this->createRoutineExercise($exMap['Hanging Leg Raises'], 3, 15, $order++));
        $routines[] = $routine4;

        // 5. Upper/Lower Strength
        $routine5 = new Routine();
        $routine5->setName('Upper Body Strength')
            ->setDescription('Focus on compound movements for strength building.')
            ->setDifficulty(Difficulty::Advanced)
            ->setGoalType(GoalType::MuscleGain);

        $order = 0;
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Bench Press'], 5, 5, $order++));
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Barbell Rows'], 5, 5, $order++));
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Overhead Press'], 4, 6, $order++));
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Pull-ups'], 4, 8, $order++));
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Barbell Curls'], 3, 8, $order++));
        $routine5->addRoutineExercise($this->createRoutineExercise($exMap['Skull Crushers'], 3, 10, $order++));
        $routines[] = $routine5;

        // 6. Cardio + Core
        $routine6 = new Routine();
        $routine6->setName('Cardio & Core')
            ->setDescription('Cardiovascular and core strengthening workout.')
            ->setDifficulty(Difficulty::Beginner)
            ->setGoalType(GoalType::Endurance);

        $order = 0;
        $routine6->addRoutineExercise($this->createRoutineExercise($exMap['Treadmill Running'], 1, 20, $order++));
        $routine6->addRoutineExercise($this->createRoutineExercise($exMap['Plank'], 3, 45, $order++));
        $routine6->addRoutineExercise($this->createRoutineExercise($exMap['Hanging Leg Raises'], 3, 12, $order++));
        $routine6->addRoutineExercise($this->createRoutineExercise($exMap['Cable Crunches'], 3, 15, $order++));
        $routines[] = $routine6;

        return $routines;
    }

    private function createRoutineExercise(Exercise $exercise, ?int $sets, ?int $reps, int $order): RoutineExercise
    {
        return (new RoutineExercise())
            ->setExercise($exercise)
            ->setSets($sets)
            ->setReps($reps)
            ->setOrderIndex($order);
    }

    private function createDiets(array $foods): array
    {
        $diets = [];
        $foodMap = [];
        foreach ($foods as $food) {
            $foodMap[$food->getName()] = $food;
        }

        // 1. Muscle Gain Diet (3000 kcal)
        $diet1 = new Diet();
        $diet1->setName('Muscle Building')
            ->setDescription('High protein diet for muscle growth with caloric surplus.')
            ->setDailyKcal(3000)
            ->setGoalType(GoalType::MuscleGain);

        // Breakfast
        $breakfast1 = $this->createMeal($diet1, 'Breakfast', 1);
        $breakfast1->addMealFood($this->createMealFood($foodMap['Eggs'], 150)); // ~3 eggs
        $breakfast1->addMealFood($this->createMealFood($foodMap['Oats'], 80));
        $breakfast1->addMealFood($this->createMealFood($foodMap['Banana'], 120));
        $breakfast1->addMealFood($this->createMealFood($foodMap['Almonds'], 20));

        // Lunch
        $lunch1 = $this->createMeal($diet1, 'Lunch', 1);
        $lunch1->addMealFood($this->createMealFood($foodMap['Chicken Breast'], 200));
        $lunch1->addMealFood($this->createMealFood($foodMap['Brown Rice'], 200));
        $lunch1->addMealFood($this->createMealFood($foodMap['Broccoli'], 150));
        $lunch1->addMealFood($this->createMealFood($foodMap['Olive Oil'], 15));

        // Dinner
        $dinner1 = $this->createMeal($diet1, 'Dinner', 1);
        $dinner1->addMealFood($this->createMealFood($foodMap['Salmon Fillet'], 180));
        $dinner1->addMealFood($this->createMealFood($foodMap['Sweet Potato'], 250));
        $dinner1->addMealFood($this->createMealFood($foodMap['Asparagus'], 150));

        // Snack 1
        $snack1a = $this->createMeal($diet1, 'Morning Snack', 1);
        $snack1a->addMealFood($this->createMealFood($foodMap['Greek Yogurt'], 200));
        $snack1a->addMealFood($this->createMealFood($foodMap['Blueberries'], 100));

        // Snack 2
        $snack1b = $this->createMeal($diet1, 'Evening Snack', 1);
        $snack1b->addMealFood($this->createMealFood($foodMap['Cottage Cheese'], 150));
        $snack1b->addMealFood($this->createMealFood($foodMap['Whole Wheat Bread'], 50));

        $diets[] = $diet1;

        // 2. Weight Loss Diet (1800 kcal)
        $diet2 = new Diet();
        $diet2->setName('Fat Loss')
            ->setDescription('High protein, moderate carb diet for fat loss while preserving muscle.')
            ->setDailyKcal(1800)
            ->setGoalType(GoalType::WeightLoss);

        $breakfast2 = $this->createMeal($diet2, 'Breakfast', 1);
        $breakfast2->addMealFood($this->createMealFood($foodMap['Eggs'], 100));
        $breakfast2->addMealFood($this->createMealFood($foodMap['Spinach'], 100));
        $breakfast2->addMealFood($this->createMealFood($foodMap['Whole Wheat Bread'], 40));

        $lunch2 = $this->createMeal($diet2, 'Lunch', 1);
        $lunch2->addMealFood($this->createMealFood($foodMap['Turkey Breast'], 150));
        $lunch2->addMealFood($this->createMealFood($foodMap['Quinoa'], 150));
        $lunch2->addMealFood($this->createMealFood($foodMap['Bell Peppers'], 100));

        $dinner2 = $this->createMeal($diet2, 'Dinner', 1);
        $dinner2->addMealFood($this->createMealFood($foodMap['Tuna'], 150));
        $dinner2->addMealFood($this->createMealFood($foodMap['Spinach'], 150));
        $dinner2->addMealFood($this->createMealFood($foodMap['Olive Oil'], 10));

        $snack2 = $this->createMeal($diet2, 'Snack', 1);
        $snack2->addMealFood($this->createMealFood($foodMap['Apple'], 150));
        $snack2->addMealFood($this->createMealFood($foodMap['Almonds'], 15));

        $diets[] = $diet2;

        // 3. Maintenance Diet (2400 kcal)
        $diet3 = new Diet();
        $diet3->setName('Balanced Maintenance')
            ->setDescription('Well-balanced diet for maintaining current weight and overall health.')
            ->setDailyKcal(2400)
            ->setGoalType(GoalType::Maintenance);

        $breakfast3 = $this->createMeal($diet3, 'Breakfast', 1);
        $breakfast3->addMealFood($this->createMealFood($foodMap['Oats'], 60));
        $breakfast3->addMealFood($this->createMealFood($foodMap['Banana'], 100));
        $breakfast3->addMealFood($this->createMealFood($foodMap['Peanut Butter'], 20));
        $breakfast3->addMealFood($this->createMealFood($foodMap['Greek Yogurt'], 100));

        $lunch3 = $this->createMeal($diet3, 'Lunch', 1);
        $lunch3->addMealFood($this->createMealFood($foodMap['Chicken Breast'], 160));
        $lunch3->addMealFood($this->createMealFood($foodMap['Whole Wheat Pasta'], 120));
        $lunch3->addMealFood($this->createMealFood($foodMap['Broccoli'], 150));
        $lunch3->addMealFood($this->createMealFood($foodMap['Olive Oil'], 12));

        $dinner3 = $this->createMeal($diet3, 'Dinner', 1);
        $dinner3->addMealFood($this->createMealFood($foodMap['Lean Ground Beef'], 150));
        $dinner3->addMealFood($this->createMealFood($foodMap['Brown Rice'], 180));
        $dinner3->addMealFood($this->createMealFood($foodMap['Avocado'], 80));

        $snack3a = $this->createMeal($diet3, 'Afternoon Snack', 1);
        $snack3a->addMealFood($this->createMealFood($foodMap['Cottage Cheese'], 120));
        $snack3a->addMealFood($this->createMealFood($foodMap['Orange'], 130));

        $snack3b = $this->createMeal($diet3, 'Evening Snack', 1);
        $snack3b->addMealFood($this->createMealFood($foodMap['Eggs'], 100));

        $diets[] = $diet3;

        // 4. Endurance Diet (2800 kcal)
        $diet4 = new Diet();
        $diet4->setName('Endurance Athlete')
            ->setDescription('High carbohydrate diet for endurance training and performance.')
            ->setDailyKcal(2800)
            ->setGoalType(GoalType::Endurance);

        $breakfast4 = $this->createMeal($diet4, 'Breakfast', 1);
        $breakfast4->addMealFood($this->createMealFood($foodMap['Oats'], 100));
        $breakfast4->addMealFood($this->createMealFood($foodMap['Banana'], 120));
        $breakfast4->addMealFood($this->createMealFood($foodMap['Blueberries'], 80));
        $breakfast4->addMealFood($this->createMealFood($foodMap['Apple'], 80));

        $lunch4 = $this->createMeal($diet4, 'Lunch', 1);
        $lunch4->addMealFood($this->createMealFood($foodMap['Chicken Breast'], 140));
        $lunch4->addMealFood($this->createMealFood($foodMap['Sweet Potato'], 300));
        $lunch4->addMealFood($this->createMealFood($foodMap['Asparagus'], 120));

        $dinner4 = $this->createMeal($diet4, 'Dinner', 1);
        $dinner4->addMealFood($this->createMealFood($foodMap['Salmon Fillet'], 160));
        $dinner4->addMealFood($this->createMealFood($foodMap['Quinoa'], 200));
        $dinner4->addMealFood($this->createMealFood($foodMap['Bell Peppers'], 120));

        $snack4a = $this->createMeal($diet4, 'Pre-Workout', 1);
        $snack4a->addMealFood($this->createMealFood($foodMap['Banana'], 100));
        $snack4a->addMealFood($this->createMealFood($foodMap['Peanut Butter'], 25));

        $snack4b = $this->createMeal($diet4, 'Post-Workout', 1);
        $snack4b->addMealFood($this->createMealFood($foodMap['Greek Yogurt'], 200));
        $snack4b->addMealFood($this->createMealFood($foodMap['Orange'], 100));

        $diets[] = $diet4;

        return $diets;
    }

    private function createMeal(Diet $diet, string $name, int $dayOfWeek): Meal
    {
        $meal = (new Meal())
            ->setName($name)
            ->setDayOfWeek($dayOfWeek);
        $diet->addMeal($meal);
        return $meal;
    }

    private function createMealFood(Food $food, string $quantityG): MealFood
    {
        return (new MealFood())
            ->setFood($food)
            ->setQuantityG($quantityG);
    }
}
