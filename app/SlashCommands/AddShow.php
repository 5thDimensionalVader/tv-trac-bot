<?php

namespace App\SlashCommands;

use App\Models\Show;
use Laracord\Commands\SlashCommand;
use Discord\Parts\Interactions\Command\Option;
use Illuminate\Support\Facades\Http;
use Discord\Parts\Interactions\Interaction;

class AddShow extends SlashCommand
{
    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'add-show';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Register a new show to the bot';

    /**
     * The command options.
     *
     * @var array
     */
    protected $options = [
        [
            'name' => 'title',
            'description' => 'Add the name of the TV show',
            'type' => Option::STRING,
            'required' => true,
        ],
    ];

    /**
     * The permissions required to use the command.
     *
     * @var array
     */
    protected $permissions = [];

    /**
     * Indiciates whether the command requires admin permissions.
     *
     * @var bool
     */
    protected $admin = false;

    /**
     * Indicates whether the command should be displayed in the commands list.
     *
     * @var bool
     */
    protected $hidden = false;

    /**
     * Handle the slash command.
     *
     * @param  \Discord\Parts\Interactions\Interaction  $interaction
     * @return void
     */
    public function handle($interaction): void
    {
        $title = $interaction->data->options->offsetGet('title')->value;
//        TODO: change $tv_shows to $ftv_response to be more descriptive.
        $tv_shows = $this->findTvShowByTitle($title);

        if(isset($tv_shows) && is_array($tv_shows)){
            $message = $this->message()
                ->title("Select a show")
                ->content("Kindly select a show from the options to register it!");

            foreach ($tv_shows as $show){
                $show = json_decode($show, true);
                $date_formatted = explode('-', $show['premiered'])[0];
                $message->button("{$show['name']} ($date_formatted)", function (Interaction $interaction) use ($show) {
//                    TODO: change $func_res to $string_response to be more descriptive.
                    $string_response = $this->findTvShowById($interaction->data->custom_id);
                    if(isset($string_response) && is_string($string_response)){
                        return $interaction->respondWithMessage(
                            $this
                                ->message()
                                ->title('Something went wrong')
                                ->content("$string_response")
                                ->error()
                                ->build(), ephemeral: true
                        );
                    } else {
                        return $interaction->respondWithMessage(
                            $this->message()
                                ->title("Show registered!")
                                ->content("Nice! You've added {$show['name']} to be tracked!")
                                ->success()
                                ->build(),
                            ephemeral: true
                        );
                    }
                }, options: ["custom_id" => $show['thetvdb']]);
            }

            $interaction->respondWithMessage(
                $message->build(),
                ephemeral: true
            );
        } elseif (isset($tv_shows) && is_string($tv_shows)) {
            $interaction->respondWithMessage(
                $this
                    ->message()
                    ->title('Something went wrong')
                    ->content("$tv_shows")
                    ->error()
                    ->build(), ephemeral: true
            );
        } else {
            $interaction->respondWithMessage(
                $this
                    ->message()
                    ->title('Show registered!')
                    ->content("Nice! You've added {$title} to be tracked!")
                    ->success()
                    ->build()
            );
        }
    }

    private function findTvShowByTitle(string $title): array | string
    {
        $base_url = env("BASE_URL");
        $response = Http::get("$base_url/search/shows?q=$title");

        if($response->ok()){
            if (count($response->json()) > 1){
                $temp = [];
                foreach ($response->json() as $data){
                    if($data['show']['status'] !== "In Development"){
                        $temp[] = json_encode([
                            'name' => $data['show']['name'],
                            'image' => $data['show']['image']['medium'],
                            'thetvdb' => $data['show']['externals']['thetvdb'],
                            'premiered' => $data['show']['premiered'],
                        ]);
                    }
                }
                return $temp;
            } else {
                if ($response->json()[0]['show']['status'] === "Running"){
                    return $this->createShow($response->json()[0]['show']['name'], $response->json()[0]['show']['id']);
                } else {
                    return "This show is currently not running!";
                }
            }
        } else {
            $status = (string) $response->status();
            $this->console()->error("$status");
        }
    }

    private function findTvShowById(int $id): string
    {
        $base_url = env("BASE_URL");
        $response = Http::get("$base_url/lookup/shows?thetvdb=$id");

        if($response->ok()){
            if($response->json()['status'] === "Running"){
                $this->createShow($response->json()['name'], $response->json()['id']);
                return $this->createShow($response->json()['name'], $response->json()['id']);
            }
        } else {
            $status = (string) $response->status();
            $this->console()->error("$status");
        }
    }

    private function createShow($name, $show_id): string
    {
        try {
//            TODO: try chaining a where and firstOr together.
            $show = Show::firstOrCreate(['show_id' => $show_id], ['name' => $name]);

            if($show){
                return "$show->name already exists in the database!";
            }
        } catch (\Exception $e){
            $this->console()->error("$e");
        }
    }
}
