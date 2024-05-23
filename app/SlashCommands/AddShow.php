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
    public function handle($interaction)
    {
        $title = $interaction->data->options->offsetGet('title')->value;
        $tv_shows = $this->findTvShowByTitle($title);

        if(isset($tv_shows) && is_array($tv_shows)){
            $message = $this->message()
                ->title("Select a show")
                ->content("Kindly select a show from the options to register it!");

            foreach ($tv_shows as $show){
                $show = json_decode($show, true);
                $date_formatted = explode('-', $show['premiered'])[0];
                $message->button("{$show['name']} ($date_formatted)", function (Interaction $interaction) use ($show) {
                    $func_res = $this->findTvShowById($interaction->data->custom_id);
                    $reply =  $this->message()
                        ->title($show['name'] === $func_res ? "Showed added!": "Something went wrong")
                        ->content($show['name'] === $func_res ? "Yay! You've added $func_res to be tracked!": "$func_res");
                    if($show['name'] === $func_res){
                        $reply->success();
                    } else {
                        $reply->error();
                    }

                    return $interaction->respondWithMessage(
                        $reply->build()
                    );
                }, options: ["custom_id" => $show['thetvdb']]);
            }

            $interaction->respondWithMessage(
                $message->build()
            );
        } elseif (isset($tv_shows) && is_string($tv_shows)) {
            $interaction->respondWithMessage(
                $this
                    ->message()
                    ->title('Something went wrong')
                    ->content("$tv_shows")
                    ->build()
            );
        } else {
            $interaction->respondWithMessage(
                $this
                    ->message()
                    ->title('Show added!')
                    ->content("Yay! You've added {$title} to be tracked!")
                    ->build()
            );
        }
    }

    private function findTvShowByTitle(string $title)
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

    private function findTvShowById(int $id)
    {
        $base_url = env("BASE_URL");
        $response = Http::get("$base_url/lookup/shows?thetvdb=$id");

        if($response->ok()){
            if($response->json()['status'] === "Running"){
                $created = $this->createShow($response->json()['name'], $response->json()['id']);
                if(isset($created) && is_string($created)){
                    return $created;
                } else {
                    return $response->json()['name'];
                }
            }
        } else {
            $status = (string) $response->status();
            $this->console()->error("$status");
        }
    }

    private function createShow($name, $show_id)
    {
//        TODO: if the unique index is not used for the $show_id, find a logic to check if the $show_id already exists in the database.
//        TODO: if the unique index is used, make use of firstOrCreate() method and handle the message response.
//        $create_show = new Show;
//        $res = $create_show->where('show_id', $show_id)->get();
//        if($res->contains($show_id)){
//            return "This show is already registered, thank you!";
//        } else {
//            $create_show->name = $name;
//            $create_show->show_id = $show_id;
//
//            $create_show->save();
//        }
        Show::create([
            'name' => $name,
            'show_id' => $show_id
        ]);
    }
}
