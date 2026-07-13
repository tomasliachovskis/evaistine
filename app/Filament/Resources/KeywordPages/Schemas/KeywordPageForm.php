<?php

namespace App\Filament\Resources\KeywordPages\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class KeywordPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->helperText('URL: /akcijos/{slug}'),
                TextInput::make('title')
                    ->label('Pavadinimas')
                    ->required()
                    ->maxLength(255),
                TextInput::make('emoji')
                    ->label('Emoji')
                    ->maxLength(16)
                    ->helperText('Rodomas hub ir hero bloke, pvz. ☕'),
                TextInput::make('grammar_plural')
                    ->label('Gramatika: plural')
                    ->maxLength(64)
                    ->helperText('Antraštėms: „Pigiausi {plural}“'),
                TextInput::make('grammar_genitive')
                    ->label('Gramatika: genitive')
                    ->maxLength(64)
                    ->helperText('Antraštėms: „… {genitive} pasiūlymų“'),
                TextInput::make('grammar_dative')
                    ->label('Gramatika: dative')
                    ->maxLength(64)
                    ->helperText('Antraštėms: „Kur {dative} akcija?“'),
                TextInput::make('h1')
                    ->label('H1 antraštė')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Iš primary_keywords[0]. Meta title/description generuojami runtime.')
                    ->columnSpanFull(),
                TagsInput::make('primary_keywords')
                    ->label('Primary keywords (top 3 exact)')
                    ->helperText('H1 = [0], meta title = [1], meta description = [2]')
                    ->columnSpanFull(),
                TagsInput::make('brands')
                    ->label('Prekės ženklai / linijos')
                    ->columnSpanFull(),
                TextInput::make('meta_title')
                    ->label('Meta title (deprecated)')
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Generuojamas runtime pagal primary keywords + kainas')
                    ->columnSpanFull(),
                Textarea::make('meta_description')
                    ->label('Meta description (deprecated)')
                    ->rows(3)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Generuojamas runtime pagal primary keywords + kainas')
                    ->columnSpanFull(),
                TagsInput::make('search_terms')
                    ->label('Paieškos terminai')
                    ->required()
                    ->helperText('Meilisearch užklausos, pvz. ledai, ledų')
                    ->columnSpanFull(),
                TagsInput::make('category_slugs')
                    ->label('Kategorijų slug filtras')
                    ->helperText('Neprivaloma, pvz. saldytas-maistas-ir-ledai')
                    ->columnSpanFull(),
                TagsInput::make('exclude_terms')
                    ->label('Išskiriami terminai')
                    ->helperText('Produktai su šiais žodžiais bus praleisti')
                    ->columnSpanFull(),
                Textarea::make('intro_html')
                    ->label('Intro HTML')
                    ->rows(5)
                    ->columnSpanFull(),
                Repeater::make('tips')
                    ->label('Patarimai')
                    ->schema([
                        TextInput::make('icon')->label('Ikona (emoji)')->maxLength(8),
                        TextInput::make('title')->label('Antraštė')->required(),
                        Textarea::make('text')->label('Tekstas')->required()->rows(2),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->collapsible(),
                Repeater::make('faq')
                    ->label('DUK')
                    ->schema([
                        TextInput::make('question')->label('Klausimas')->required(),
                        Textarea::make('answer')->label('Atsakymas')->required()->rows(3),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->collapsible(),
                TagsInput::make('related_slugs')
                    ->label('Susiję keyword slug')
                    ->helperText('Kitų keyword puslapių slug sąrašas')
                    ->columnSpanFull(),
                TextInput::make('min_active_offers')
                    ->label('Min. aktyvių pasiūlymų')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('matching_offers_count')
                    ->label('Atitikmenų skaičius (DB)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Atnaujinama per keywords:refresh-counts'),
                TextInput::make('displayed_offers_count')
                    ->label('Rodomų pasiūlymų skaičius (DB)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sort_order')
                    ->label('Rikiavimas')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_published')
                    ->label('Publikuota')
                    ->default(true)
                    ->required(),
                Toggle::make('is_chip')
                    ->label('Chip')
                    ->helperText('Rodyti keyword chip eilutėje (hub, footer, kategorijos)')
                    ->default(false),
            ]);
    }
}
